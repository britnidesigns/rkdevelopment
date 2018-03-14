<?php

if ( is_user_logged_in() ) {
    global $current_user;
    wp_get_current_user();

    echo '<ul class="user-menu">
        <li><a href="'. get_site_url() .'/account" class="user">'.$current_user->user_firstname.'</a></li>
        <li><a href="'.wp_logout_url( home_url() ).'" class="logout">Log Out</a></li>
    </ul>';
} else {
    echo '<a href="'. get_site_url() .'/login" class="login">Log In</a>';
}

?>
