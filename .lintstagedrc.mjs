/**
 * lint-staged config, run by the Husky pre-commit hook.
 *
 * - Staged PHP files are checked with PHPCS using phpcs.xml.dist.
 * - A staged change to the lint config re-runs the full `composer lint`,
 *   so unchanged files are re-checked against the new rules.
 */
import { existsSync } from 'node:fs';

const phpcsMissing = ! existsSync( 'vendor/bin/phpcs' );
const installComposer = `node -e "console.error('PHPCS not installed. Run \\\`composer install\\\`, then commit again.'); process.exit(1)"`;
const quote = ( file ) => `"${ file }"`;

export default {
	'*.php': ( files ) =>
		phpcsMissing ? installComposer : `vendor/bin/phpcs ${ files.map( quote ).join( ' ' ) }`,
	'{phpcs.xml.dist,composer.json,composer.lock}': () =>
		phpcsMissing ? installComposer : 'composer lint',
};
