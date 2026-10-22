/**
 * wp-env afterStart lifecycle script: prepares a disposable wp-env site for
 * the e2e suite. Idempotent, because wp-env runs it on every start.
 *
 * - activates Zero Spam (network-wide on multisite)
 * - pretty permalinks, UTC, open registration (single site)
 * - users e2eadmin (super admin on multisite), e2eeditor, e2eauthor and,
 *   on multisite, e2esiteadmin (administrator of /site2/ only), from
 *   tests/e2e/wp-env-users.json (multisite logins cannot contain hyphens)
 * - the "E2E comments" post with comments open
 *
 * Usage (from .wp-env*.json): node tests/e2e/bin/wp-env-after-start.mjs --config=.wp-env.json
 */

import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { E2E_DIR, ROOT, checkNode, fail, wpEnvComposeFile, wpEnvSh } from './lib.mjs';

checkNode();

const configArg = process.argv.find( ( a ) => a.startsWith( '--config=' ) );
const config = configArg ? configArg.slice( '--config='.length ) : '.wp-env.json';
const wpEnvConfig = JSON.parse( readFileSync( join( ROOT, config ), 'utf8' ) );
const multisite = true === wpEnvConfig.multisite;
const users = JSON.parse( readFileSync( join( E2E_DIR, 'wp-env-users.json' ), 'utf8' ) );

const composeFile = wpEnvComposeFile( config );
if ( ! composeFile ) {
	fail( `wp-env environment for ${ config } is not initialized.` );
}

/**
 * Quotes a value for sh.
 *
 * @param {string} value Value.
 * @return {string}
 */
const q = ( value ) => `'${ String( value ).replace( /'/g, `'\\''` ) }'`;

const userLines = [ 'admin', 'editor', 'author' ].map( ( role ) => {
	const u = users[ role ];
	return `wp user get ${ q( u.user ) } --field=ID >/dev/null 2>&1 || wp user create ${ q( u.user ) } ${ q( u.email ) } --role=${ u.role } --user_pass=${ q( u.pass ) } --porcelain`;
} );

// wp-env keeps one wp-config.php per WordPress core. After switching core
// (npm run env:core:min) on an installed network, `wp core multisite-install`
// is skipped, so the new wp-config.php lacks the network constants; restore them.
const host = new URL( `http://localhost:${ wpEnvConfig.port }` ).host;
const networkConstants = [
	'wp config has MULTISITE --type=constant 2>/dev/null || {',
	'wp config set WP_ALLOW_MULTISITE true --raw --type=constant &&',
	'wp config set MULTISITE true --raw --type=constant &&',
	'wp config set SUBDOMAIN_INSTALL false --raw --type=constant &&',
	`wp config set DOMAIN_CURRENT_SITE ${ q( host ) } --type=constant &&`,
	"wp config set PATH_CURRENT_SITE '/' --type=constant &&",
	'wp config set SITE_ID_CURRENT_SITE 1 --raw --type=constant &&',
	'wp config set BLOG_ID_CURRENT_SITE 1 --raw --type=constant; }',
].join( ' ' );

const script = [
	'set -e',
	multisite ? networkConstants : '',
	// After a core switch the stored db_version differs from the code's, and
	// wp-admin would show "Database Update Required" (in both directions).
	multisite ? 'wp core update-db --network >/dev/null' : 'wp core update-db >/dev/null',
	multisite ? 'wp plugin activate zero-spam --network' : 'wp plugin activate zero-spam',
	"wp rewrite structure '/%postname%/' --hard >/dev/null",
	'wp option update timezone_string UTC >/dev/null',
	// wp-env names the site after the checkout directory; fix it so screenshots match on every checkout.
	'wp option update blogname "Zero Spam E2E" >/dev/null',
	'wp option update admin_email_lifespan 4102444800 >/dev/null',
	multisite ? 'wp network meta update 1 registration none >/dev/null' : 'wp option update users_can_register 1 >/dev/null',
	multisite ? '' : 'wp option update default_role subscriber >/dev/null',
	...userLines,
	multisite ? `wp super-admin add ${ q( users.admin.user ) } >/dev/null` : '',
	multisite ? 'wp site list --field=path | grep -qx /site2/ || wp site create --slug=site2 --title="Site 2" --email=e2e-site2@example.com --porcelain' : '',
	multisite ? `wp user get ${ q( users.siteadmin.user ) } --field=ID >/dev/null 2>&1 || wp user create ${ q( users.siteadmin.user ) } ${ q( users.siteadmin.email ) } --role=administrator --user_pass=${ q( users.siteadmin.pass ) } --url="$(wp site list --field=url | grep /site2/)" --porcelain` : '',
	multisite ? `wp user set-role ${ q( users.siteadmin.user ) } administrator --url="$(wp site list --field=url | grep /site2/)" >/dev/null` : '',
	multisite ? `wp user remove-role ${ q( users.siteadmin.user ) } --url="$(wp site list --field=url | head -1)" >/dev/null 2>&1 || true` : '',
	'test -n "$(wp post list --post_type=post --name=e2e-comments --format=ids)" || wp post create --post_type=post --post_status=publish --post_title="E2E comments" --post_name=e2e-comments --comment_status=open --post_content="A post for the Zero Spam comment tests." --porcelain',
	multisite ? 'URL2="$(wp site list --field=url | grep /site2/)"; test -n "$(wp post list --url="$URL2" --post_type=post --name=e2e-comments --format=ids)" || wp post create --url="$URL2" --post_type=post --post_status=publish --post_title="E2E comments" --post_name=e2e-comments --comment_status=open --post_content="A post for the Zero Spam comment tests." --porcelain' : '',
	multisite ? 'wp rewrite structure "/%postname%/" --hard --url="$(wp site list --field=url | grep /site2/)" >/dev/null' : '',
	'wp cache flush >/dev/null',
	'echo "zero-spam e2e: $(wp core version) / PHP $(php -r \'echo PHP_VERSION;\')"',
]
	.filter( Boolean )
	.join( '\n' );

const res = wpEnvSh( composeFile, script );
process.stdout.write( res.stdout );
if ( 0 !== res.status ) {
	process.stderr.write( res.stderr );
	fail( `wp-env afterStart failed for ${ config } (exit ${ res.status }).` );
}
