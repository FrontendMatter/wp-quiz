<?php namespace Mosaicpro\WP\Plugins\Quiz;

use Mosaicpro\WpCore\PluginGeneric;
use Mosaicpro\WpCore\PostData;
use Mosaicpro\WpCore\PostList;
use Mosaicpro\WpCore\PostStatus;
use Mosaicpro\WpCore\PostType;

/**
 * Class QuizResults
 * @package Mosaicpro\WP\Plugins\Quiz
 */
class QuizResults extends PluginGeneric
{
    /**
     * Holds a QuizResults instance
     * @var
     */
    protected static $instance;

    /**
     * Create a new QuizResults instance
     */
    public function __construct()
    {
        parent::__construct();
        $this->quizzes = Quizzes::getInstance();
    }

    /**
     * Get a QuizResults Singleton instance
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(self::$instance))
        {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Initialize QuizResults plugin
     */
    public static function init()
    {
        $instance = self::getInstance();
        $instance->initShared();
        $instance->initAdmin();
    }

    /**
     * Activate QuizResults
     */
    public static function activate()
    {
        self::getInstance()->post_type();
        flush_rewrite_rules();
    }

    /**
     * Initialize QuizResults Shared Resources
     */
    private function initShared()
    {
        $this->post_type();
        $this->post_status();
        $this->timer();
    }

    /**
     * Initialize QuizResults Admin
     * @return bool
     */
    private function initAdmin()
    {
        if (!is_admin()) return false;
        $this->admin_post_list();
    }

    /**
     * Set the cron actions to be executed for quiz timers
     */
    private function timer()
    {
        add_action('quiz_result_timer', function($saved)
        {
            wp_update_post(['ID' => (int) $saved, 'post_status' => 'failed'], true);
        });
    }

    /**
     * Create the QuizResults post type
     */
    private function post_type()
    {
        PostType::make('result', $this->prefix)
            ->setOptions([
                'public' => true,
                'show_ui' => true,
                'supports' => ['title'],
                'capability_type' => 'post',
                'capabilities' => [
                    'create_posts' => false,
                ],
                'map_meta_cap' => true,
                'show_in_menu' => $this->prefix
            ])
            ->register();
    }

    /**
     * Create the quiz results statuses
     */
    private function post_status()
    {
        $statuses = [
            'pending_evaluation',
            'evaluation_complete',
            'passed',
            'failed'
        ];

        foreach ($statuses as $status)
        {
            PostStatus::make($status, $this->prefix)
                ->setPostType($this->getPrefix('result'))
                ->register();
        }
    }

    /**
     * Customize the WP Admin post list
     */
    private function admin_post_list()
    {
        // Add Quiz Results Listing Custom Columns
        PostList::add_columns($this->getPrefix('result'), [
            ['quiz', $this->__('Quiz'), 2],
            ['status', $this->__('Status'), 3],
            ['author', $this->__('Student'), 4]
        ]);

        // Remove the title column
        PostList::remove_columns($this->getPrefix('result'), ['title']);

        // Display Quiz Results Listing Custom Columns
        PostList::bind_column($this->getPrefix('result'), function($column, $post_id)
        {
            if ($column == 'quiz')
            {
                $post = get_post($post_id);
                echo edit_post_link(get_the_title($post->post_parent));
            }
            if ($column == 'status')
                echo get_post_status($post_id);
        });
    }

    /**
     * Get the open quiz result for the current user, quiz and course
     * @param $quiz_id
     * @param bool $course_id
     * @return bool
     */
    public function get_open( $quiz_id, $course_id = false )
    {
        $open = $this->get($quiz_id, $course_id);

        if (count($open) == 0)
            return false;

        return $open[0];
    }

    /**
     * Get the closed quiz results for the current user, quiz and course
     * @param $quiz_id
     * @param bool $course_id
     * @return array|bool
     */
    public function get_closed( $quiz_id, $course_id = false )
    {
        $closed = $this->get($quiz_id, $course_id, ['post_status' => ['failed', 'passed', 'pending_evaluation']]);

        if (count($closed) == 0)
            return false;

        return $closed;
    }

    /**
     * Get quiz results for the current user, quiz and course
     * @param $quiz_id
     * @param bool $course_id
     * @param array $args
     * @return array
     */
    public function get( $quiz_id, $course_id = false, array $args = [] )
    {
        $args_default = [
            'post_type' => $this->getPrefix('result'),
            'post_status' => 'draft',
            'author' => get_current_user_id(),
            'post_parent__in' => [$quiz_id]
        ];
        if ($course_id)
        {
            $args_default['meta_key'] = 'course_id';
            $args_default['meta_value'] = $course_id;
        }
        $posts = get_posts(wp_parse_args($args, $args_default));
        return $posts;
    }

    /**
     * Create a new quiz result for the current user and quiz;
     * Also connect the result with a course_id;
     * @param $quiz_id
     * @param bool $course_id
     * @return int|\WP_Error
     */
    public function make_new( $quiz_id, $course_id = false )
    {
        $data = PostData::get_default($this->getPrefix('result'));
        $data->post_parent = $quiz_id;

        $saved = @wp_update_post($data, true);
        if (is_a($saved, 'WP_Error'))
            wp_die($saved->get_error_messages());

        if ($course_id)
            update_post_meta($saved, 'course_id', $course_id);

        $timer = $this->quizzes->get_timer($quiz_id);
        if ($timer !== false)
        {
            update_post_meta($saved, 'quiz_timer_limit', $timer);
            wp_schedule_single_event(time() + $timer, 'quiz_result_timer', [$saved]);
        }

        return $saved;
    }

    /**
     * Get a quiz result timer
     * @param $quiz_result
     * @return array|bool
     */
    public function get_timer( $quiz_result )
    {
        if (!is_a($quiz_result, 'WP_Post')) return false;

        // get the quiz timer settings
        $timer = get_post_meta($quiz_result->ID, 'quiz_timer_limit', true);
        if (empty($timer)) return false;

        $quiz_result_created = strtotime($quiz_result->post_date);
        $quiz_result_until = $quiz_result_created + $timer;
        $quiz_result_remaining = $quiz_result_until - time();

        return $this->get_timer_duration($quiz_result_remaining);
    }

    /**
     * Convert seconds to an array containing hh, mm, ss keys
     * @param $seconds
     * @return array
     */
    private function get_timer_duration($seconds)
    {
        $t = round($seconds);
        if ($seconds < 0) $t = 0;
        $hours = $t/3600;
        $minutes = $t/60%60;
        $seconds = $t%60;
        $time = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        list($hours, $minutes, $seconds) = explode(":", $time);
        return ['hh' => $hours, 'mm' => $minutes, 'ss' => $seconds];
    }

    /**
     * Get the timer for the current user and quiz result
     * @param $quiz_id
     * @param bool $course_id
     * @return array|bool
     */
    public function get_open_timer( $quiz_id, $course_id = false )
    {
        // get the open quiz result for the quiz and user
        $quiz_result = $this->get_open($quiz_id, $course_id);
        if (!$quiz_result) return false;

        // get the remaining time for the quiz result
        return $this->get_timer($quiz_result);
    }

    /**
     * Save the quiz user answer
     * @return bool
     */
    public function save_answer()
    {
        $quiz_id = $_GET['quiz_id'];
        $quiz_unit_id = get_the_ID();
        $quiz_result_id = $_GET['quiz_result'];

        $nonce = check_ajax_referer( 'quiz_answer_' . $quiz_id . $quiz_unit_id, '_wpnonce', false );
        if ( !$nonce ) wp_die( 'Security error' );

        $answer = $_POST['response'];
        if (empty($answer)) return false;

        $responses = get_post_meta($quiz_result_id, 'response', true);
        if (empty($responses)) $responses = [];

        // save the answer
        $responses[$quiz_unit_id] = $answer;
        update_post_meta($quiz_result_id, 'response', $responses);

        // if this was the last unanswered question, close the quiz result
        $quiz_units = get_post_meta($quiz_id, $this->getPrefix('unit'));
        if (count($quiz_units) == count($responses))
        {
            // update the quiz result
            $quiz_result = ['ID' => $quiz_result_id, 'post_status' => 'pending_evaluation'];
            wp_update_post($quiz_result);

            // cancel the cron set for when the timer expires
            $time = wp_next_scheduled('quiz_result_timer', [$quiz_result_id]);
            if ($time !== false) wp_unschedule_event($time, 'quiz_result_timer', [$quiz_result_id]);

            wp_redirect('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
            exit();
        }

        // refresh the page
        wp_redirect('http://'.$_SERVER['HTTP_POST'].$_SERVER['REQUEST_URI']);
        exit();
    }
} 