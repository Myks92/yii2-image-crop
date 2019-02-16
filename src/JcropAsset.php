<?php
namespace maxdancepro\image;

use yii\web\AssetBundle;

/**
 * Jcrop asset bundle.
 */
class JcropAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public $sourcePath = '@vendor/bower-asset/jcrop';
    /**
     * @inheritdoc
     */
    public $css = [
        'css/jquery.Jcrop.min.css',
    ];
    /**
     * @inheritdoc
     */
    public $js = [
        'js/jquery.Jcrop.min.js',
    ];
    /**
     * @inheritdoc
     */
    public $depends = [
        'maxdancepro\image\Asset',
    ];
}
