<?php namespace Mosaicpro\WP\Plugins\Quiz;

use Mosaicpro\WpCore\CRUD;
use Mosaicpro\WpCore\FormBuilder;
use Mosaicpro\WpCore\MetaBox;
use Mosaicpro\WpCore\PluginGeneric;
use Mosaicpro\WpCore\PostType;

/**
 * Class QuizAnswers
 * @package Mosaicpro\WP\Plugins\Quiz
 */
class QuizAnswers extends PluginGeneric
{
    /**
     * Holds a QuizAnswers instance
     * @var
     */
    protected static $instance;

    /**
     * Initialize QuizAnswers
     */
    public static function init()
    {
        $instance = self::getInstance();
        $instance->initShared();
        $instance->initAdmin();
    }

    /**
     * Activate QuizAnswers
     */
    public static function activate()
    {
        self::getInstance()->post_types();
        flush_rewrite_rules();
    }

    /**
     * Get a QuizAnswers Singleton instance
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
     * Initialize QuizAnswers Shared Resources
     */
    private function initShared()
    {
        $this->post_types();
    }

    /**
     * Initialize QuizAnswers Admin
     * @return bool
     */
    private function initAdmin()
    {
        if (!is_admin()) return false;
        $this->metaboxes();
    }

    /**
     * Create the QuizAnswers post type
     */
    public function post_types()
    {
        PostType::make('answer', $this->prefix)
            ->setOptions(['supports' => ['title'], 'show_in_menu' => $this->prefix])
            ->register();
    }

    /**
     * Create the Meta Boxes
     */
    private function metaboxes()
    {
        // Quiz Answer Attributes
        MetaBox::make($this->prefix, 'answer_attributes', $this->__('Answer Attributes'))
            ->setPostType($this->getPrefix('answer'))
            ->setField('correct', $this->__('The answer is correct'), 'checkbox')
            ->register();
    }

    /**
     * Get Quiz Answers CRUD Form
     * @return callable
     */
    public function getCrudForm()
    {
        return function($post)
        {
            FormBuilder::checkbox('correct', $this->__('The answer is correct'), 1, esc_attr($post->correct) == 1);
        };
    }

    /**
     * Get all Quiz Answers by $quiz_unit_id
     * @param $quiz_unit_id
     * @return array
     */
    public function get($quiz_unit_id)
    {
        $quiz_answers = get_post_meta($quiz_unit_id, $this->getPrefix('answer'));
        $answers = get_posts([
            'post_type' => [$this->getPrefix('answer')],
            'post__in' => $quiz_answers
        ]);

        $order = $this->get_order($quiz_unit_id);
        $answers = CRUD::order_sortables($answers, $order);

        return $answers;
    }

    /**
     * Get the order of Quiz Answers associated with $quiz_unit_id
     * @param $quiz_unit_id
     * @return mixed
     */
    public function get_order($quiz_unit_id)
    {
        $order_key = '_order_' . $this->getPrefix('answer');
        $order = get_post_meta($quiz_unit_id, $order_key, true);
        return !empty($order) ? $order : get_post_meta($quiz_unit_id, $this->getPrefix('answer'));
    }
} 