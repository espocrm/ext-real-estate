# Real State extension for EspoCRM

## Configuration

Create `config.json` file in the root directory. This config will be merged with `config-default.json`. You can override default parameters in the created config.

Parameters:

* espocrm.repository - from what repository to fetch EspoCRM;
* espocrm.branch - what branch to fetch (`stable` is set by default); you can specify version number instead (e.g. `5.8.5`);
* database - credentials of the dev database;
* install.siteUrl - site url of the dev instance.


## Config for EspoCRM instance

You can override EspoCRM config. Create `config.php` in the root directory of the repository. This file will be applied after EspoCRM intallation (when building).

Example:

```php
<?php
return [
    'useCacheInDeveloperMode' => true,
];
```

## Building

After building, EspoCRM instance with installed extension will be available at `site` directory. You will be able to access it with credentials:

* Username: admin
* Password: 1

Note: You can build on Linux and Windows OS. Commands are the same.

### Preparation

1. You need to have *node*, *npm*, *composer* installed.
2. Run `npm install`.
3. Create a database. The database name is set in the config file.

### Full EspoCRM instance building

It will download EspoCRM (from the repository specified in the config), then build and install it. Then it will install the extension.

Command:

```
node build --all
```

Note: It will remove a previously installed EspoCRM instance, but keep the database intact.

### Copying extension files to EspoCRM instance

You need to run this command every time you make changes in `src` directory and you want to try these changes on Espo instance.

Command:

```
node build --copy
```

### Running after-install script

AfterInstall.php will be applied for EspoCRM instance.

Command:

```
node build --after-install
```

### Extension package building

Command:

```
node build --extension
```

The package will be created in `build` directory.

Note: The version number is taken from `package.json`.

## Development workflow

1. Do development in `src` dir.
2. Run `node build --copy`.
3. Test changes in EspoCRM instance at `site` dir by opening it in a browser. A URL will look like: `http://localhost/real-estate/site`, depending on how you named your directory.

## Tests

Prepare:

1. `node build --copy`
2. `cd site`
3. `grunt test`

Unit tests:

```
vendor/bin/phpunit --bootstrap=./vendor/autoload.php tests/unit/Espo/Modules/RealEstate
```

Integration tests:

```
vendor/bin/phpunit --bootstrap=./vendor/autoload.php tests/integration/Espo/Modules/RealEstate
```

## Versioning

The version number is stored in `package.json` and `package-lock.json`.

Bumping version:

```
npm version patch
npm version minor
npm version major
```

## Translation

Assuming that you have already built EspoCRM instance.

### Building PO file

1. Change dir: `cd site`
2. Run: `node po en_US --module=RealEstate` (replace `en_US` with a language code you need to translate to)

This will generate PO file in `site/build/` directory. You will need to translate this file.

### Building langauge files from PO

Assuming you have translated PO file in build directory with the same name as when it was generated.

1. Change dir: `cd site`
2. Run: `node lang en_US --module=RealEstate` (replace `en_US` with the target language code)

This will generate language files in `site/build/` directory. You will need to copy these files to `src/files/` directory and commit.

## License

Real Estate extension for EspoCRM is published under the GNU GPLv3 license.
