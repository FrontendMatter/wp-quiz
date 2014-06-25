<?php
/**
 * The template for displaying a single Quiz Result
 *
 * You can edit the single quiz result template by creating a single-mp_quiz_result.php template
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
use Mosaicpro\HtmlGenerators\Panel\Panel;
use Mosaicpro\WP\Plugins\Quiz\Quizzes;

$quizzes = Quizzes::getInstance();
$quiz_id = false;

get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

    <?php
    $result = get_post();
    $quiz_id = $result->post_parent;
    $course_id = get_post_meta(get_the_ID(), 'course_id', true);
    ?>

    <div class="row">
        <div class="col-md-9">

            <h2><?php echo $quizzes->__('Quiz Results'); ?></h2>
            <hr/>

            <?php
            $responses = get_post_meta(get_the_ID(), 'response', true);
            if (empty($responses)) $responses = [];

            if (count($responses) == 0)
                echo '<p>' . $quizzes->__('The student did not provide any answer.') . '</p>';
            else
            {
                $questions = count(get_post_meta($quiz_id, $quizzes->getPrefix('unit')));
                echo '<p>The student answered to ' . count($responses) . ' out of ' . $questions . ' questions.</p>';

                $num = 1;
                foreach ($responses as $quiz_unit_id => $response)
                {
                    $quiz_unit = get_post($quiz_unit_id);
                    $response_panel = Panel::make('default');
                    $response_value = esc_attr($response);
                    $response_panel->addTitle( $num . '). ' . $quiz_unit->post_content );
                    $response_panel->addBody($response_value);
                    echo $response_panel;
                    $num++;
                }
            }
            ?>

        </div>
        <div class="col-md-3">

            <?php $quiz_url = get_permalink( $quiz_id ) . '?course_id=' . get_post_meta(get_the_ID(), 'course_id', true); ?>

            <?php echo Media::make()
                ->addObjectLeft(get_avatar( get_the_author(), 64 ))
                ->addBody($quizzes->__('Student'), get_the_author()); ?>

            <hr/>
            <div>
                <p>
                    <strong><?php echo $quizzes->__('Quiz'); ?>: </strong> <a href="<?php echo $quiz_url; ?>"><?php echo get_the_title($quiz_id); ?></a><br/>
                    <strong><?php echo $quizzes->__('Course'); ?>: </strong> <a href="<?php echo get_post_permalink($course_id); ?>"><?php echo get_the_title($course_id); ?></a>
                </p>
                <p>
                    <strong><?php echo $quizzes->__('Status'); ?>:</strong> <?php echo get_post_status(); ?><br/>
                    <strong><?php echo $quizzes->__('Score'); ?>:</strong> 10/100<br/>
                    <strong><?php echo $quizzes->__('Percentile'); ?>:</strong> 10%
                </p>

                <?php echo Button::success($quizzes->__('Retake Quiz'))->addUrl( $quiz_url ); ?>
            </div>

        </div>
    </div>
    <hr/>

<?php endwhile; endif; ?>
<?php get_footer(); ?>