<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package rentr
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">

	<?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/css/common.css">
</head>

<body <?php body_class(); ?>>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'rentr' ); ?></a>

	<header id="masthead" class="site-header">
		<div class="site-branding">
			<?php the_custom_logo();?>
			<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>

			<?php
			$description = get_bloginfo( 'description', 'display' );
			if ( $description || is_customize_preview() ) : ?>
				<p class="site-description"><?php echo $description; /* WPCS: xss ok. */ ?></p>
			<?php
			endif; ?>
		</div><!-- .site-branding -->

		<nav id="site-navigation" class="main-navigation">
			<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false"><?php esc_html_e( 'Primary Menu', 'rentr' ); ?></button>
			<?php
                if ( is_user_logged_in() ) {
                    wp_nav_menu( array(
    					'theme_location' => 'logged-in-nav',
    					'menu_id'        => 'primary-menu',
    					'container_class'=> 'primary-nav'
    				) );

                    global $current_user;
                    wp_get_current_user();

                    echo '<ul class="user-menu">
                        <li>'.$current_user->display_name.'</li>
                        <li><a href="'.wp_logout_url().'">Log Out</a></li>
                    </ul>';
                } else {
                    wp_nav_menu( array(
    					'theme_location' => 'menu-1',
    					'menu_id'        => 'primary-menu',
                        'container_class'=> 'primary-nav'
    				) );

                    echo '<a href="'.wp_login_url().'" class="login">Log In</a>';
                }
            ?>
		</nav><!-- #site-navigation -->
	</header><!-- #masthead -->

	<div id="content" class="site-content">
