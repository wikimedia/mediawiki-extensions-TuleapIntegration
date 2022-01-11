# TuleapIntegration

## Installation
Execute

    composer require mediawiki/tuleap-integration ~1
within MediaWiki root or add `mediawiki/tuleap-integration` to the
`composer.json` file of your project

## Activation
Add

    wfLoadExtension( 'TuleapIntegration' );
to your `LocalSettings.php` file.