<?php namespace Mosaicpro\WP\Plugins\Quiz;

use Mosaicpro\HtmlGenerators\Core\IoC;
use Mosaicpro\WpCore\CRUD;
use Mosaicpro\WpCore\Date;
use Mosaicpro\WpCore\MetaBox;
use Mosaicpro\WpCore\PluginGeneric;
use Mosaicpro\WpCore\PostList;
use Mosaicpro\WpCore\PostType;
use Mosaicpro\WpCore\ThickBox;
use Mosaicpro\WpCore\Utility;

/**
 * Class Quizzes
 * @package Mosaicpro\WP\Plugins\Quiz
 */
class Quizzes extends PluginGeneric
{
    /**
     * Holds a Quizzes Instance
     * @var
     */
    protected static $instance;

    /**
     * Create a new Quizzes Instance
     */
    public function __construct()
    {
        parent::__construct();
        $this->quizUnits = QuizUnits::getInstance();
    }

    /**
     * Get a Quizzes Singleton instance
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
     * Initialize the Quizzes plugin
     */
    public static function init()
    {
        $instance = self::getInstance();

        // i18n
        $instance->loadTextDomain();

        // Load Plugin Templates into the current Theme
        $instance->plugin->initPluginTemplates();

        // Initialize Shared Resources
        $instance->initShared();

        // Initialize Admin Resources
        $instance->initAdmin();

        // Get the Container from IoC
        $app = IoC::getContainer();

        // Bind the Quizzes to the Container
        $app->bindShared('quizzes', function() use ($instance)
        {
            return $instance;
        });
    }

    /**
     * Activate Quizzes
     */
    public static function activate()
    {
        $instance = self::getInstance();
        $instance->post_types();
        flush_rewrite_rules();
    }

    /**
     * Initialize Shared Resources
     */
    private function initShared()
    {
        $this->post_types();
    }

    /**
     * Initialize Admin Resources
     * @return bool
     */
    private function initAdmin()
    {
        if (!is_admin()) return false;

        $this->admin_menu();
        $this->metaboxes();
        $this->crud();
        $this->admin_post_list();
    }

    /**
     * Create the admin menu
     */
    private function admin_menu()
    {
        add_action('admin_menu', function()
        {
            add_menu_page($this->__('Quizzes'), $this->__('Quizzes'), 'edit_posts', $this->prefix, '', 'dashicons-editor-help', '27.002');
        });
    }

    /**
     * Create the Quizez post type
     */
    private function post_types()
    {
        PostType::make(['quiz', 'quizzes'], $this->prefix)
            ->setOptions(['show_in_menu' => $this->prefix])
            ->register();
    }

    /**
     * Create the Quizez Meta Boxes
     */
    private function metaboxes()
    {
        // Quiz -> Quiz Units Meta Box
        MetaBox::make($this->prefix, 'unit', $this->__('Quiz Units'))
            ->setPostType($this->getPrefix('quiz'))
            ->setDisplay([
                CRUD::getListContainer([$this->getPrefix('unit')]),
                ThickBox::register_iframe( 'thickbox_units', $this->__('Add Unit to Quiz'), 'admin-ajax.php',
                    ['action' => 'list_' . $this->getPrefix('quiz') . '_' . $this->getPrefix('unit')] )->render()
            ])
            ->register();

        // Quiz -> Timer Meta Box
        MetaBox::make($this->prefix, 'timer', $this->__('Quiz Timer'))
            ->setPostType($this->getPrefix('quiz'))
            ->setField('timer_enabled', $this->__('Enable/Disable the Quiz Timer'), ['true' => $this->__('On'), 'false' => $this->__('Off')], 'radio')
            ->setField('timer_limit', $this->__('Set the timer limit (hh:mm:ss)'), 'select_hhmmss')
            ->register();

        // Only show the Quiz Timer Limit if the Quiz Timer is enabled
        Utility::show_hide([
                'when' => '#' . $this->getPrefix('timer'),
                'attribute' => 'value',
                'is_value' => 'true',
                'show_target' => '#' . $this->getPrefix('timer') . ' .form-group'
            ],[$this->getPrefix('quiz')]
        );

        // Quiz Scoring
        MetaBox::make($this->prefix, 'scoring', $this->__('Scoring'))
            ->setPostType($this->getPrefix('quiz'))
            ->setField('score_min', $this->__('Quiz Passing Score'), [1, 100], 'select_range')
            ->register();
    }

    /**
     * Create Quizez CRUD Relationships
     */
    private function crud()
    {
        // Quizez -> Quiz Units CRUD Relationship
        CRUD::make($this->prefix, $this->getPrefix('quiz'), $this->getPrefix('unit'))
            ->setListFields($this->getPrefix('unit'), $this->quizUnits->getCrudListFields())
            ->setForm($this->getPrefix('unit'), $this->quizUnits->getCrudForm())
            ->register();

        CRUD::setPostTypeLabel($this->getPrefix('quiz'), $this->__('Quiz'));
        CRUD::setPostTypeLabel($this->getPrefix('unit'), $this->__('Quiz Unit'));
    }

    /**
     * Customize the WP Admin post listing for Quizez
     */
    private function admin_post_list()
    {
        // Add Quizez Listing Custom Columns
        PostList::add_columns($this->getPrefix('quiz'), [
            ['quiz_units', $this->__('Quiz Units'), 2]
        ]);

        // Display Quizez Listing Custom Columns
        PostList::bind_column($this->getPrefix('quiz'), function($column, $post_id)
        {
            if ($column == 'quiz_units')
            {
                $units = get_post_meta($post_id, $this->getPrefix('unit'));
                echo count($units) . ' ' . $this->__('Quiz Units');
            }
        });
    }

    /**
     * Get the timer limit in seconds or in the original format stored in DB for a quiz;
     * If the quiz does not have a limit, it returns false;
     * @param $quiz_id
     * @param bool $seconds
     * @return bool|mixed
     */
    public function get_timer( $quiz_id, $seconds = true )
    {
        $quiz_timer_enabled = get_post_meta($quiz_id, 'timer_enabled', true);
        $quiz_timer_limit = get_post_meta($quiz_id, 'timer_limit', true);

        if (!empty($quiz_timer_enabled) && !empty($quiz_timer_limit) && $quiz_timer_enabled === 'true')
            return $seconds ? Date::time_to_seconds(implode(":", $quiz_timer_limit)) : $quiz_timer_limit;

        return false;
    }
}