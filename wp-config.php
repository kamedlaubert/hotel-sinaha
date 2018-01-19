<?php
/**
 * La configuration de base de votre installation WordPress.
 *
 * Ce fichier contient les réglages de configuration suivants : réglages MySQL,
 * préfixe de table, clefs secrètes, langue utilisée, et ABSPATH.
 * Vous pouvez en savoir plus à leur sujet en allant sur
 * {@link http://codex.wordpress.org/fr:Modifier_wp-config.php Modifier
 * wp-config.php}. C'est votre hébergeur qui doit vous donner vos
 * codes MySQL.
 *
 * Ce fichier est utilisé par le script de création de wp-config.php pendant
 * le processus d'installation. Vous n'avez pas à utiliser le site web, vous
 * pouvez simplement renommer ce fichier en "wp-config.php" et remplir les
 * valeurs.
 *
 * @package WordPress
 */

// ** Réglages MySQL - Votre hébergeur doit vous fournir ces informations. ** //
/** Nom de la base de données de WordPress. */
define('DB_NAME', 'sinaha_db');

/** Utilisateur de la base de données MySQL. */
define('DB_USER', 'root');

/** Mot de passe de la base de données MySQL. */
define('DB_PASSWORD', '');

/** Adresse de l'hébergement MySQL. */
define('DB_HOST', 'localhost');

/** Jeu de caractères à utiliser par la base de données lors de la création des tables. */
define('DB_CHARSET', 'utf8mb4');

/** Type de collation de la base de données.
  * N'y touchez que si vous savez ce que vous faites.
  */
define('DB_COLLATE', '');

/**#@+
 * Clefs uniques d'authentification et salage.
 *
 * Remplacez les valeurs par défaut par des phrases uniques !
 * Vous pouvez générer des phrases aléatoires en utilisant
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ le service de clefs secrètes de WordPress.org}.
 * Vous pouvez modifier ces phrases à n'importe quel moment, afin d'invalider tous les cookies existants.
 * Cela forcera également tous les utilisateurs à se reconnecter.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '(7 ~Idi[bOp~_1ozKU&8#e6^t>XRZ[&4,w<1_m#`O4Gf<u~E8D{f9Jp7}GVn9F7m');
define('SECURE_AUTH_KEY',  'aK8c[AA`F68BWuU$_x=E[Li^`e{%lNzEdyO_*kLqYrx0BihsGgjKs&ixc(Tf_cg]');
define('LOGGED_IN_KEY',    '7_*/A}EeE1+-4 %Qc`)={Lyl4!-LELaw|Wc1oU@g|w&O&lb?JK1j2kA._Y h>Yn3');
define('NONCE_KEY',        'p~>6f/jE)h{dHDEo:G8=Yfo<p8?8]-6?r[:riQNQ-oXm~?f~*A[x(|hc66:oMpVL');
define('AUTH_SALT',        'SpZ<*LzC0Mj1YbBEr-zW$_DAF&(fYtMvUG%Z_g<YKa%D8Yk{][|L?G}dwL^ltc`*');
define('SECURE_AUTH_SALT', 'wn[fb=&[)[R~<LT/)Akb}K>k{J{eDD[ZhP=G!w-Y`JXf3,lIra62yml2kP|44uDb');
define('LOGGED_IN_SALT',   '$x8/lSvX2>~ #AYYE!C5c6R;MV,u1UUpmZxF*]j9@oio9wf<b8Qvs)z(xKwe>(p?');
define('NONCE_SALT',       '$bibl^.0pikVc9/:U_(x)%y$k1MpGcdBG[Ur4gc~8m=4A1XrT^&o&!i.=F{xD_-|');
/**#@-*/

/**
 * Préfixe de base de données pour les tables de WordPress.
 *
 * Vous pouvez installer plusieurs WordPress sur une seule base de données
 * si vous leur donnez chacune un préfixe unique.
 * N'utilisez que des chiffres, des lettres non-accentuées, et des caractères soulignés!
 */
$table_prefix  = 'wp_';

/**
 * Pour les développeurs : le mode déboguage de WordPress.
 *
 * En passant la valeur suivante à "true", vous activez l'affichage des
 * notifications d'erreurs pendant vos essais.
 * Il est fortemment recommandé que les développeurs d'extensions et
 * de thèmes se servent de WP_DEBUG dans leur environnement de
 * développement.
 *
 * Pour plus d'information sur les autres constantes qui peuvent être utilisées
 * pour le déboguage, rendez-vous sur le Codex.
 * 
 * @link https://codex.wordpress.org/Debugging_in_WordPress 
 */
define('WP_DEBUG', false);

/* C'est tout, ne touchez pas à ce qui suit ! Bon blogging ! */

/** Chemin absolu vers le dossier de WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once(ABSPATH . 'wp-settings.php');