<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost:10005' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          '}l{>79QP=WbLXG05Pl$4M<j>2M)Z^/~|xPTVhQ#vw}HGKM,?LELm!0).P[Y3GPd ' );
define( 'SECURE_AUTH_KEY',   'G{erttlH#rm/o Vj:z#@LiInW.InqL@Gx2oIk:7<1d5u>wc)237~Q8>n*]~M6/A[' );
define( 'LOGGED_IN_KEY',     'w(T,:k:+zDk8ml<1~kjAMyx2_`dkh41YLUAP5GuX-4fplJq=Cc3i9-qS^o{aN7[u' );
define( 'NONCE_KEY',         'e#cIM6eJ>c r!>rOdQ_]9kOPu$ko+Uzdnb?UYt,S^yv:b]S_.9i.5pvCCrG4.4I#' );
define( 'AUTH_SALT',         'a{)@Mxp;EBmtkV2-V=sg88q+8|-,ipN^+pzpRYmqoq6Bf@fO 0eT~`*C;cP;q~M ' );
define( 'SECURE_AUTH_SALT',  'o9@&rVyiJvnK|,yw51<:y0pVX{fI9=&,Ru0JXZ4GlJ=S6L:rI`V]jPMH/J+aLKpi' );
define( 'LOGGED_IN_SALT',    'YZ=6<=))GHKWymbSl,N{%bMJ9ogYc_--m>>eI~8vP;N[yYJ}2{v`e(~9 d^}rJ`G' );
define( 'NONCE_SALT',        '4x10$UnC,I&Y]4kFtF,f+~dxnYG2&G)2Xw+AW&6]^lEG4Zx9G 2&Pe%*>ZOBAM.J' );
define( 'WP_CACHE_KEY_SALT', '/bMut3W{P&DgJi2?#FI5fN&{-zuwIYy~q!Hr`vtA}8W_7Q-e`(p{F_Zepo]9!/fU' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
