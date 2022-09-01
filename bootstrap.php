<?php
/**
 * Created for plugin-core-logistic
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

use Leadvertex\Plugin\Components\Batch\BatchContainer;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Db\Components\PluginReference;
use Leadvertex\Plugin\Components\Form\Autocomplete\AutocompleteRegistry;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Info\Developer;
use Leadvertex\Plugin\Components\Info\Info;
use Leadvertex\Plugin\Components\Info\PluginType;
use Leadvertex\Plugin\Components\Settings\Settings;
use Leadvertex\Plugin\Components\Translations\Translator;
use Leadvertex\Plugin\Core\Actions\Upload\LocalUploadAction;
use Leadvertex\Plugin\Core\Actions\Upload\UploadersContainer;
use Leadvertex\Plugin\Core\Logistic\Components\BatchShippingHandler;
use Leadvertex\Plugin\Core\Logistic\Components\Waybill\WaybillContainer;
use Leadvertex\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerInterface;
use Medoo\Medoo;
use XAKEPEHOK\Path\Path;

# 1. Configure DB (for SQLite *.db file and parent directory should be writable)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('testDB.db'),
    'debug_mode' => true,
]));

# 2. Set plugin default language
Translator::config('ru_RU');

# 3. Set permitted file extensions (* for any ext) and max sizes (in bytes). Pass empty array for disable file uploading
UploadersContainer::addDefaultUploader(new LocalUploadAction([
    'jpg' => 100 * 1024,       //Max 100 KB for *.jpg file
    'zip' => 10 * 1024 * 1024, //Max 10 MB for *.zip archive
]));

# 4. Configure info about plugin
Info::config(
    new PluginType(PluginType::MACROS),
    fn() => Translator::get('info', 'Plugin name'),
    fn() => Translator::get('info', 'Plugin markdown description'),
    ["country" => "RU"],
    new Developer(
        'Your (company) name',
        'support.for.plugin@example.com',
        'example.com',
    )
);
