<?php
/**
 * The template for displaying a single Quiz
 *
 * You can edit the single quiz template by creating a single-mp_quiz_quiz.php template
 * in your theme. You can use this template as a guide or starting point.
 *
 * For a list of available custom functions to use inside this template,
 * please refer to the Developer's Guide or the Documentation
 *
 ***************** NOTICE: *****************
 * Do not make changes to this file. Any changes made to this file
 * will be overwritten if the plugin is updated.
 *
 * To overwrite this template with your own, make a copy of it (with the same name)
 * in your theme directory. WordPress will automatically load the template you create
 * in your theme directory instead of this one.
 *
 * See Theme Integration Guide for more information
 ***************** NOTICE: *****************
 */

use Mosaicpro\HtmlGenerators\Button\Button;
use Mosaicpro\HtmlGenerators\ButtonGroup\ButtonGroup;
use Mosaicpro\HtmlGenerators\ListGroup\ListGroup;
use Mosaicpro\HtmlGenerators\Panel\Panel;
use Mosaicpro\WP\Plugins\Quiz\QuizAnswers;
use Mosaicpro\WP\Plugins\Quiz\QuizResults;
use Mosaicpro\WP\Plugins\Quiz\QuizUnits;
use Mosaicpro\WP\Plugins\Quiz\Quizzes;
use Mosaicpro\WpCore\FormBuilder;
use Mosaicpro\WpCore\Taxonomy;

$quizzes = Quizzes::getInstance();
$quizAnswers = QuizAnswers::getInstance();
$quizUnits = QuizUnits::getInstance();
$quizResults = QuizResults::getInstance();

$quiz_id = false;
$course_id = isset($_REQUEST['course_id']) ? $_REQUEST['course_id'] : false;
if (get_post_type() == $quizzes->getPrefix('quiz'))
    $quiz_id = get_the_ID();

if (get_post_type() == $quizzes->getPrefix('unit'))
    $quiz_id = $_REQUEST['quiz_id'];

$page_params = ['quiz_id' => $quiz_id, 'course_id' => $course_id];
$post_type = get_post_type();
if ($post_type == $quizzes->getPrefix('unit'))
{
    $quiz_result = $_GET['quiz_result'];
    $page_params['quiz_result'] = $quiz_result;
}

$page_params = http_build_query($page_params);
get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

    <div class="row">
        <div class="col-md-9">

            <?php if ($post_type == $quizzes->getPrefix('quiz')): ?>

                <h2>Take Quiz Page Template</h2>
                <p><strong>The quiz introduction page template content goes here.</strong> Lorem ipsum dolor sit amet, consectetur adipisicing elit. Accusamus ad amet animi architecto autem cumque, earum eos et eveniet excepturi, inventore ipsa nemo praesentium quisquam quo veniam, vitae. Aliquid, exercitationem!</p>

            <?php elseif ($post_type == $quizzes->getPrefix('unit')): ?>

                <h4><?php the_title(); ?></h4>
                <hr/>

                <?php echo Panel::make('default')
                        ->addTitle('Question')
                        ->addBody(get_the_content()); ?>

                <?php
                $term = Taxonomy::get_term(get_the_ID(), 'quiz_unit_type');
                $meta = get_post_meta(get_the_ID());

                $formbuilder = new FormBuilder();

                $panel = Panel::make('default');
                $panel_body = '';

                $responses = get_post_meta($quiz_result, 'response', true);
                if (empty($responses)) $responses = [];
                $response_value = '';
                if (!empty($responses[get_the_ID()])) $response_value = is_array($responses[get_the_ID()]) ? $responses[get_the_ID()] : esc_attr($responses[get_the_ID()]);

                if ($term->slug == 'essay')
                    $panel_body .= $formbuilder->get_textarea('response', $quizzes->__('Write your answer'), $response_value);

                if ($term->slug == 'true_false')
                    $panel_body .= $formbuilder->get_radio('response', $quizzes->__('Select the correct answer'), $response_value, [$quizzes->__('True'), $quizzes->__('False')]);

                if ($term->slug == 'one_word')
                    $panel_body .= $formbuilder->get_input('response', $quizzes->__('Provide the correct answer with a single word'), $response_value);

                if ($term->slug == 'multiple_choice')
                    $panel_body .= $formbuilder->get_checkbox_multiple('response[]', $quizzes->__('Select one or more answers'), $response_value, $formbuilder::select_values($quizAnswers->get(get_the_ID()), null));

                $panel->addBody($panel_body);

                $next_post = $quizUnits->get_next($quiz_id, get_the_ID());
                $prev_post = $quizUnits->get_prev($quiz_id, get_the_ID());
                $next_post_link = $next_post ? get_post_permalink($next_post) . '?' . $page_params : false;
                $prev_post_link = $prev_post ? get_post_permalink($prev_post) . '?' . $page_params : false;

                $quiz_navigation = ButtonGroup::make();
                if ($prev_post_link)
                    $quiz_navigation->add(Button::regular('Previous Unit')->addUrl($prev_post_link));
                if ($next_post_link)
                    $quiz_navigation->add(Button::regular('Next Unit')->addUrl($next_post_link));

                $buttons_grid = Grid::make();
                $buttons_grid->addColumn(6, Button::success('Save Answer')->isSubmit());
                $buttons_grid->addColumn(6, $quiz_navigation,
                    ['class' => 'text-right']
                );
                ?>

                <form action="" method="post">
                    <?php wp_nonce_field('quiz_answer_' . $quiz_id . get_the_ID()); ?>
                    <?php echo $panel; ?>
                    <?php echo $buttons_grid; ?>
                </form>

            <?php endif; ?>

        </div>
        <div class="col-md-3">

            <?php if ($post_type == $quizzes->getPrefix('unit')): ?>

                <?php
                $timer = $quizzes->get_timer($quiz_id);
                if ($timer !== false):
                ?>

                <h4>Time Remaining</h4>
                <hr/>

                <div id="quiz-unit-timer"></div>
                <hr/>

                <?php endif; ?>

            <?php endif; ?>

            <h4><?php echo $quizzes->__('Quiz navigation'); ?></h4>

            <?php
            if ($post_type == $quizzes->getPrefix('quiz') || current_user_can('administrator'))
            {
                $button_introduction = Button::regular($quizzes->__('Quiz Introduction'))
                    ->addUrl(get_the_permalink($quiz_id) . '?' . $page_params)->isBlock()
                    ->setClass(get_post_type() == $quizzes->getPrefix('quiz') ? 'btn-primary' : '');

                $button_course = Button::regular('Back to Course')->addUrl(get_the_permalink($course_id))->isBlock();

                if ($post_type == $quizzes->getPrefix('quiz'))
                {
                    echo $button_introduction;
                    echo $button_course;
                    echo "<hr/>";
                }
            }
            ?>

            <?php
            $units = $quizUnits->get($quiz_id);

            if ($post_type == $quizzes->getPrefix('quiz') && $units->have_posts())
            {
                $first_unit_id = $quizUnits->get_first($quiz_id);
                echo Button::success($quizzes->__('Start Quiz'))->isBlock()->addUrl(get_the_permalink($first_unit_id) . '?' . $page_params);
            }

            if ($post_type == $quizzes->getPrefix('unit') && $units->have_posts())
            {
                $unit_id = get_the_ID();
                $units_list = ListGroup::make();
                while ($units->have_posts())
                {
                    $units->the_post();
                    $units_list->addLink(get_the_permalink() . '?' . $page_params, get_the_title())->isActive($unit_id == get_the_ID());
                }
                echo $units_list;
            }
            ?>

            <?php
            if ($post_type != $quizzes->getPrefix('quiz') && current_user_can('administrator'))
            {
                echo "<hr/><h4>" . $quizzes->__('Admin Options') . "</h4>";
                echo "<p>" . $button_introduction . $button_course . "</p>";
                echo "<small>" . $quizzes->__("Note: Once the quiz has started, these options are only visible to Instructors and Admins for preview purposes;") . "</small>";
            }

            if ($post_type == $quizzes->getPrefix('quiz'))
            {
                $history = $quizResults->get_closed($quiz_id, $course_id);
                if ($history)
                {
                    $history_list = ListGroup::make();
                    foreach ($history as $history_item)
                        $history_list->addLink( get_permalink($history_item->ID), $history_item->post_modified);
                    ?>
                    <hr/>
                    <h4><?php echo $quizzes->__('History'); ?></h4>
                    <?php
                    echo $history_list;
                }
            }
            ?>

        </div>
    </div>
    <hr/>

<?php endwhile; endif; ?>
<?php get_footer(); ?>