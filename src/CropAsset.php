<?php
namespace maxdancepro\image;

use yii\web\AssetBundle;

/**
 * Crop asset bundle.
 */
class CropAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public $sourcePath = '@maxdancepro/image/assets';
    /**
     * @inheritdoc
     */
    public $js = [
        'js/jcrop.js',
    ];
    /**
     * @inheritdoc
     */
    public $depends = [
        'maxdancepro\image\JcropAsset',
    ];
}
