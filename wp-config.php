<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );


/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '20Hr14kyR#Qg?<cpPx*/QZw4_uuV(*Iyo..+vNMj9@8Z7Y@^~8l6U/K&!9XiPJL,' );
define( 'SECURE_AUTH_KEY',  'tde:(jRp#D@4$t1~X )rWL0d[lx3R[0DYe+NuL+tvW*] )ef}%}ky4T*h,^wv@7T' );
define( 'LOGGED_IN_KEY',    ']Bq5,YSKE`g0N6uxcKhL(AbloP~g?Fse= ubN]//?,Ry.C+3_VSCrh^Gk+TEZo^b' );
define( 'NONCE_KEY',        'IzfK;0LG[bGL9DXDEPT;k:^uKQU$;>WnF:AX4Zu,!e1|59YdmbR0]J,L{db/8ozh' );
define( 'AUTH_SALT',        '@|Q9aP^y=JXn6!!bafP0Th4WDE}cG]<P2+*F2V12o-Iba_C4WCd&@ko_r-4k.pJn' );
define( 'SECURE_AUTH_SALT', '$xbNufpD-}F;_&~WUZJ2Fnx:P[SjD_fJCW&iPmWU uh/}?}J/8)>:9ENl2 U `j1' );
define( 'LOGGED_IN_SALT',   'hJn7mvARpTV2;p|Qu2(50B`K6|3Urd/69l2- SO=G;b<duV}8+S{Tes.r/1V+Gzh' );
define( 'NONCE_SALT',       'zSI1Rw?,CRVrOL#T1f{ Mt41P,/,}]+xnEy:FedrmOHo]UHV@mb<~o^rS@LSeK74' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
