<?php namespace Mosaicpro\WP\Plugins\Quiz;

use Mosaicpro\HtmlGenerators\Core\IoC;
use Mosaicpro\WpCore\CRUD;
use Mosaicpro\WpCore\FormBuilder;
use Mosaicpro\WpCore\MetaBox;
use Mosaicpro\WpCore\PluginGeneric;
use Mosaicpro\WpCore\PostList;
use Mosaicpro\WpCore\PostType;
use Mosaicpro\WpCore\Taxonomy;
use Mosaicpro\WpCore\ThickBox;
use Mosaicpro\WpCore\Utility;
use WP_Query;

/**
 * Class QuizUnits
 * @package Mosaicpro\WP\Plugins\Quiz
 */
class QuizUnits extends PluginGeneric
{
    /**
     * Holds a QuizUnits instance
     * @var
     */
    protected static $instance;

    /**
     * Create a new QuizUnits instance
     */
    public function __construct()
    {
        parent::__construct();
        $this->quizAnswers = QuizAnswers::getInstance();
    }

    /**
     * Get a QuizUnits Singleton instance
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
     * Initialize QuizUnits
     */
    public static function init()
    {
        $instance = self::getInstance();
        $instance->initShared();
        $instance->initFront();
        $instance->initAdmin();
    }

    /**
     * Activate QuizUnits
     */
    public static function activate()
    {
        $instance = self::getInstance();
        $instance->taxonomies();

        wp_insert_term('Essay','quiz_unit_type', ['slug' => 'essay']);
        wp_insert_term('Multiple choice','quiz_unit_type', ['slug' => 'multiple_choice']);
        wp_insert_term('True or False','quiz_unit_type', ['slug' => 'true_false']);
        wp_insert_term('One Word Answer','quiz_unit_type', ['slug' => 'one_word']);

        $instance->post_types();
        flush_rewrite_rules();
    }

    /**
     * Initialize QuizUnits Shared Resources
     */
    private function initShared()
    {
        $this->post_types();
        $this->taxonomies();
    }

    /**
     * Initialize QuizUnits Admin Resources
     * @return bool
     */
    private function initAdmin()
    {
        if (!is_admin()) return false;

        $this->metaboxes();
        $this->crud();
        $this->admin_post_list();
    }

    /**
     * Initialize QuizUnits Front Resources
     */
    private function initFront()
    {
        if (is_admin()) return false;

        $this->initTakeQuiz();
        $this->initQuizTimer();
    }

    /**
     * Acts as a controller when accessing a Quiz Unit page in the front
     */
    private function initTakeQuiz()
    {
        add_action('template_redirect', function()
        {
            global $post;

            if (!is_single()) return false;
            if ($post->post_type !== $this->getPrefix('unit')) return false;
            if (empty($_GET['quiz_id']) || empty($_GET['course_id']))
            {
                wp_redirect( home_url() );
                exit();
            }

            $QuizResults = QuizResults::getInstance();

            if (empty($_GET['quiz_result']))
            {
                $open = $QuizResults->get_open($_GET['quiz_id']);
                if (!$open) $quiz_result = $QuizResults->make_new($_GET['quiz_id'], $_GET['course_id']);
                else $quiz_result = $open->ID;

                wp_redirect( get_permalink($post->ID) . '?' . http_build_query(array_merge($_GET, ['quiz_result' => $quiz_result])) );
                exit();
            }

            $result = get_post($_GET['quiz_result']);
            if (!$result) return false;
            if ($result->post_status != 'draft')
            {
                wp_redirect( get_permalink( $result->ID ) );
                exit();
            }

            // if this unit is already answered, go to the next unit
            $responses = get_post_meta($result->ID, 'response', true);
            if (empty($responses)) $responses = [];
            if (array_key_exists($post->ID, $responses))
            {
                $page_params = http_build_query(['quiz_id' => $_GET['quiz_id'], 'course_id' => $_GET['course_id'], 'quiz_result' => $_GET['quiz_result']]);
                $next_post = QuizUnits::get_next($_GET['quiz_id'], $post->ID);
                $next_post_link = $next_post ? get_post_permalink($next_post) . '?' . $page_params : false;
                if ($next_post_link)
                {
                    wp_redirect($next_post_link);
                    exit();
                }
            }

            // process the user answer
            if ($_POST)
                $QuizResults->save_answer();

        });
    }

    /**
     * Enqueue the Quiz Timer scripts
     */
    private function initQuizTimer()
    {
        add_action('wp_enqueue_scripts', function()
        {
            if (!(is_single() && get_post_type() == $this->getPrefix('unit'))) return false;

            // get the timer for the open quiz result
            $QuizResults = QuizResults::getInstance();
            $quiz_result_timer = $QuizResults->get_open_timer( $_GET['quiz_id'], $_GET['course_id'] );
            if (!$quiz_result_timer) return false;

            wp_enqueue_script( 'jquery-countdown', plugin_dir_url( $this->plugin->getPluginFile() ) . 'assets/jquery-countdown/jquery.countdown.js', ['jquery'], null, true );
            wp_enqueue_style( 'jquery-countdown', plugin_dir_url( $this->plugin->getPluginFile() ) . 'assets/jquery-countdown/jquery.countdown.css', null, null );
            wp_enqueue_script( 'mp-lms-quiz-unit', plugin_dir_url( $this->plugin->getPluginFile() ) . 'assets/quiz-unit/timer.js', ['jquery', 'jquery-countdown'], null, true );
            wp_enqueue_style( 'mp-lms-quiz-unit', plugin_dir_url( $this->plugin->getPluginFile() ) . 'assets/quiz-unit/timer.css', null, null );
            wp_localize_script('mp-lms-quiz-unit', 'quiz_unit_timer', [
                'duration' => $quiz_result_timer
            ]);
        });
    }

    /**
     * Create the QuizUnits post type
     */
    private function post_types()
    {
        PostType::make('unit', $this->prefix)
            ->setOptions(['supports' => ['title'], 'show_in_menu' => $this->prefix])
            ->register();
    }

    /**
     * Create Taxonomies
     */
    public static function taxonomies()
    {
        $plugin = IoC::getContainer('plugin');
        Taxonomy::make('quiz unit type')
            ->setType('radio')
            ->setPostType($plugin->getPrefix('unit'))
            ->setOption('update_meta_box', [
                'label' => $plugin->__('Quiz Unit Type'),
                'context' => 'normal',
                'priority' => 'high'
            ])
            ->register();
    }

    /**
     * Create Meta Boxes
     */
    private function metaboxes()
    {
        // Unit Question Editor
        add_action('edit_form_after_title', function($post)
        {
            if ($post->post_type !== $this->getPrefix('unit')) return;
            echo '<h2>' . $this->__('Unit Question') . '</h2>';
            wp_editor($post->post_content, 'editpost', ['textarea_name' => 'post_content', 'textarea_rows' => 5]);
            echo "<br/>";
        });

        // Quiz Units -> Multiple Choice -> Quiz Answers Meta Box
        MetaBox::make($this->prefix, 'answer_multiple_choice', $this->__('Multiple Choice Answers'))
            ->setPostType($this->getPrefix('unit'))
            ->setDisplay([
                CRUD::getListContainer([$this->getPrefix('answer')]),
                ThickBox::register_iframe( 'thickbox_answers', $this->__('Add Answers'), 'admin-ajax.php',
                    ['action' => 'list_' . $this->getPrefix('unit') . '_' . $this->getPrefix('answer')] )->render()
            ])
            ->register();

        // Quiz Units -> True or False -> Correct Answer Meta Box
        MetaBox::make($this->prefix, 'answer_true_false', $this->__('True of False Answer'))
            ->setPostType($this->getPrefix('unit'))
            ->setField('correct_answer_true_false', $this->__('Select the correct answer'), [$this->__('True'), $this->__('False')], 'radio')
            ->register();

        // Quiz Units -> One Word -> Correct Answer Meta Box
        MetaBox::make($this->prefix, 'answer_one_word', $this->__('One Word Correct Answer'))
            ->setPostType($this->getPrefix('unit'))
            ->setField('correct_answer_one_word', $this->__('Provide the correct answer'), 'input')
            ->setDisplay([
                'fields',
                $this->__('<p><strong>Note:</strong> This is only required if the Quiz Unit should be auto-evaluated by the system. On manual evaluation by an Instructor, you can skip this option. You can also provide a list of multiple words or word variations separated with a comma; If you provide a list, then any word from the list will be treated as the correct answer.</p>')
            ])
            ->register();

        $types = ['multiple_choice', 'true_false', 'one_word'];
        foreach($types as $type)
        {
            // Only show the $type Meta Box if the Quiz Unit Type is $type
            Utility::show_hide([
                    'when' => '#quiz_unit_typechecklist',
                    'is_value' => $type,
                    'show_target' => '#' . $this->getPrefix('answer') . '_' . $type
                ],[$this->getPrefix('unit')]
            );
        }

        // Quiz Unit Scoring
        MetaBox::make($this->prefix, 'unit_scoring', $this->__('Scoring'))
            ->setPostType($this->getPrefix('unit'))
            ->setField('score_max', $this->__('Max. Score'), [1, 100], 'select_range')
            ->register();
    }

    /**
     * Create CRUD Relationships
     */
    private function crud()
    {
        // Quiz Units -> Quiz Answers CRUD Relationship
        CRUD::make($this->prefix, $this->getPrefix('unit'), $this->getPrefix('answer'))
            ->setListFields($this->getPrefix('answer'), [
                'ID',
                'crud_edit_post_title',
                'yes_no_correct'
            ])
            ->setForm($this->getPrefix('answer'), $this->quizAnswers->getCrudForm())
            ->register();

        CRUD::setPostTypeLabel($this->getPrefix('answer'), $this->__('Quiz Answer'));
    }

    /**
     * Customize the WP Admin post listing
     */
    private function admin_post_list()
    {
        // Add Quiz Units Listing Custom Columns
        PostList::add_columns($this->getPrefix('unit'), [
            ['type', 'Unit Type', 2]
        ]);

        // Display Quiz Units Listing Custom Columns
        PostList::bind_column($this->getPrefix('unit'), function($column, $post_id)
        {
            if ($column == 'type')
            {
                $type = Taxonomy::get_term($post_id, 'quiz_unit_type');
                $output = '<strong>' . $type->name . '</strong>';
                if ($type->slug == 'multiple_choice')
                {
                    $answers = count(get_post_meta($post_id, $this->getPrefix('answer')));
                    $output .= ' <em>(' . sprintf( $this->__('%1$s Answers'), $answers ) . ')</em>';
                }
                echo $output;
            }
        });
    }

    /**
     * Get Quiz Units CRUD Form
     * @return callable
     */
    public function getCrudForm()
    {
        return function($post)
        {
            FormBuilder::select_range('score_max', $this->__('Max. Score'), $post->score_max, [1, 100]);
        };
    }

    /**
     * Get Quiz Units CRUD List Fields
     * @return array
     */
    public function getCrudListFields()
    {
        return [
            'ID',
            'post_title',
            // Quiz Unit Type
            function($post)
            {
                $type = Taxonomy::get_term($post->ID, 'quiz_unit_type');
                $output = '<strong>' . $type->name . '</strong>';
                if ($type->slug == 'multiple_choice') {
                    $answers = count(get_post_meta($post->ID, $this->getPrefix('answer')));
                    $output .= ' <em>(' . sprintf( $this->__('%1$s Answers'), $answers ) . ')</em>';
                }
                return [
                    'field' => $this->__('Quiz Unit Type'),
                    'value' => $output
                ];
            },
            // Quiz Unit Max. Score
            function($post)
            {
                return [
                    'field' => $this->__('Max. Score'),
                    'value' => !empty($post->score_max) ? $post->score_max : 1
                ];
            }
        ];
    }

    /**
     * Get all Quiz Units by $quiz_id
     * @param $quiz_id
     * @return WP_Query
     */
    public function get($quiz_id)
    {
        $quiz_units = get_post_meta($quiz_id, $this->getPrefix('unit'));
        $units = new WP_Query([
            'post_type' => $this->getPrefix('unit'),
            'post__in' => $quiz_units
        ]);

        $order = $this->get_order($quiz_id);
        $posts = CRUD::order_sortables($units->posts, $order);
        $units->posts = $posts;

        wp_reset_postdata();
        return $units;
    }

    /**
     * Get the first Quiz Unit id associated with $quiz_id
     * @param $quiz_id
     * @return bool
     */
    public function get_first($quiz_id)
    {
        $order = $this->get_order($quiz_id);
        if (empty($order))
        {
            $quiz_units = get_post_meta($quiz_id, $this->getPrefix('unit'));
            if (empty($quiz_units)) return false;
            return $quiz_units[0];
        }
        return $order[0];
    }

    /**
     * Get the next Quiz Unit id after $current_unit_id associated with $quiz_id
     * @param $quiz_id
     * @param $current_unit_id
     * @return bool
     */
    public function get_next($quiz_id, $current_unit_id)
    {
        $order = $this->get_order($quiz_id);
        $current_key = array_search($current_unit_id, $order);
        if (!isset($order[$current_key])) return false;
        if (!isset($order[$current_key+1])) return false;
        return $order[$current_key+1];
    }

    /**
     * Get the previous Quiz Unit id before $current_unit_id associated with $quiz_id
     * @param $quiz_id
     * @param $current_unit_id
     * @return bool
     */
    public function get_prev($quiz_id, $current_unit_id)
    {
        $order = $this->get_order($quiz_id);
        $current_key = array_search($current_unit_id, $order);
        if (!isset($order[$current_key])) return false;
        if (!isset($order[$current_key-1])) return false;
        return $order[$current_key-1];
    }

    /**
     * Get the order of Quiz Units associated with $quiz_id
     * @param $quiz_id
     * @return mixed
     */
    public function get_order($quiz_id)
    {
        $order_key = '_order_' . $this->getPrefix('unit');
        $order = get_post_meta($quiz_id, $order_key, true);
        return !empty($order) ? $order : get_post_meta($quiz_id, $this->getPrefix('unit'));
    }
}