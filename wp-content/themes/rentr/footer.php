<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package rentr
 */

?>

	</div><!-- #content -->

	<footer id="colophon" class="site-footer">
        <a href="https://www.facebook.com/apartmentsanddevelopment/" class="facebook">
            <i class="fab fa-facebook"></i>
            <span>Get updates about new properties</span>
        </a>
        <?php
            wp_nav_menu( array(
                'theme_location' => 'footer-nav',
                'container_class'=> 'nav'
            ) );
        ?>
		<p>&copy; 2018 RK Development LC</p>
	</footer><!-- #colophon -->
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
