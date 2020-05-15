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

After building, EspoCRM instance with installed extension will be available at `site` directory.

You can access it with credentials:

* Username: admin
* Password: 1

### Preparation

1. You need to have *node*, *npm*, *composer* installed.
2. Run `npm install`.

### Full EspoCRM instance building

It will download EspoCRM, build, install it, then install the extension.

Command:

```
node build --all
```

Note: It will remove a previously installed EspoCRM instance, but keep the database intact.

### Copying extension files to EspoCRM instance

Command:

```
node build --copy
```

### Running after-install script

Command:

```
node build --afterInstall
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
3. Test changes in EspoCRM instance at `site` dir.

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

## License

Real Estate extension for EspoCRM is published under the GNU GPLv3 license.
