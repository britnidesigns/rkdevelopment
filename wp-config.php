<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'rkdev');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'z^kFA&u]OdX49j;s0&!.%X.IVZ,KTO9+p(Px@XFC(?:?|kO*u[J5Q>E_TF14K~Vv');
define('SECURE_AUTH_KEY',  'K_YRgsyC)W[#.#53!0!*+mN<e*Yw5Pa=b;~|dcZ6v0I =|G8[2t0~>DyGq`jJAz=');
define('LOGGED_IN_KEY',    ')adStSU`{10/_*XVWIrZo>m[)]<OQc(BSIW&!*jrjo$~#Iu.QentAzt!V:5n%,dl');
define('NONCE_KEY',        ' tN.sC8Vw<2/{^Un;MTZEw;OsWLS-u2/oQ_=4[ifxxHBK|)2e=(K4rOf$KV]/[k9');
define('AUTH_SALT',        'AFRyS).o]6Nl!sR%OhY+SBA+Y`*wcrS6k~!nYVBoP^X$wr$Y9hvx=kaGu5T`M@UV');
define('SECURE_AUTH_SALT', '@R_#J!ArAIu>l:|jtXCoJ:Ty0>JNCfmx7q@jJ>g<WBmj<7*nptRs1sHM8*`y-~pe');
define('LOGGED_IN_SALT',   ':osW??B!x~u*C]*)}G;;Ek[l>?}xF((v?mZ`-x?fMDWB}Wai`[uYf<*-EGm&Np?3');
define('NONCE_SALT',       'B2?UxCfaDqS!Vh-moN2i5GaN*$n9VH&]n/`n~>-y9&%A&|%}nCDt*n3^qF!F=8wX');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
