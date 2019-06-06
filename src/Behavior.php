<?php

namespace maxdancepro\image;

use Imagine\Image\Box;
use Imagine\Image\Point;
use Yii;
use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use yii\web\UploadedFile;

/**
 * Class model behavior for uploadable and cropable image
 *
 * Usage in your model:
 * ```
 * ...
 * public function behaviors()
 * {
 *     return [
 *         [
 *              'class' => \maxdancepro\image\Behavior::className(),
 *              'savePathAlias' => '@web/images/',
 *              'urlPrefix' => 'images/',
 *              'thumbUrl' => 'images/thumb',
 *              'fileName' => 'avatar.jpg',
 *              'deleteFileName' => 'avatar.jpg',
 *              'crop' => true,
 *              'attributes' => [
 *                  'avatar' => [
 *                      'savePathAlias' => '@web/images/avatars/',
 *                      'urlPrefix' => '/images/avatars/',
 *                      'width' => 100,
 *                      'height' => 100,
 *                  ],
 *                  'logo' => [
 *                      'crop' => false,
 *                      'thumbnails' => [
 *                          'savePathAlias' => '@web/images/avatars/logo', //Путь сохранения миниатюр
 *                          'mini' => [
 *                              'width' => 50,
 *                          ],
 *                      ],
 *                  ],
 *              ],
 *         ],
 *     //other behaviors
 *     ];
 * }
 * ...
 * ```
 */
class Behavior extends \yii\base\Behavior
{
    /**
     * @var array list of attribute as attributeName => options. Options:
     *  $width image width
     *  $height image height
     *  $savePathAlias @see maxdancepro\image\Behavior::$savePathAlias
     *  $crop @see maxdancepro\image\Behavior::$crop
     *  $urlPrefix @see maxdancepro\image\Behavior::$urlPrefix
     *  $thumbnails - array of thumbnails as prefix => options. Options:
     *          $width thumbnail width
     *          $height thumbnail height
     *          $savePathAlias @see maxdancepro\image\Behavior::$savePathAlias
     *          $urlPrefix @see maxdancepro\image\Behavior::$urlPrefix
     */
    public $attributes = [];
    /**
     * @var string. Default '@frontend/web/images/%className%/' or '@app/web/images/%className%/'
     */
    public $savePathAlias;
    /**
     * @var string. Путь сохранения миниатюр. По умолчанию $savePathAlias
     */
    public $thumbPath;
    /**
     * @var bool enable/disable crop.
     */
    public $crop = true;
    /**
     * @var string part of url for image without hostname. Default '/images/%className%/'
     */
    public $urlPrefix;
    /**
     * @var string. string part of url for thumbnail simage without hostname. Default '/images/%className%/'
     */
    public $thumbUrl;
    /**
     * @var string, название файла. По умолчанию уникальный генератор числа @see https://www.php.net/manual/ru/function.uniqid.php
     */
    public $fileName;
    /**
     * @var string имя файла, который необходимо удалить перед загрузкой нового. Например "$model->fileName" //filename.jpg
     */
    public $deleteFileName;

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            Model::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            Model::EVENT_BEFORE_VALIDATE => 'beforeSave',
        ];
    }

    /**
     * function for EVENT_BEFORE_VALIDATE
     */
    public function beforeValidate()
    {
        /* @var $model ActiveRecord */
        $model = $this->owner;
        foreach ($this->attributes as $attr => $options) {
            $this->ensureAttribute($attr, $options);
            if ($file = UploadedFile::getInstance($model, $attr)) {
                $model->{$attr} = $file;
            }
        }
    }

    /**
     * @param $attr
     * @param $options
     */
    public static function ensureAttribute(&$attr, &$options)
    {
        if (!is_array($options)) {
            $attr = $options;
            $options = [];
        }
    }

    /**
     * function for EVENT_BEFORE_INSERT and EVENT_BEFORE_UPDATE
     */
    public function beforeSave()
    {
        /* @var $model ActiveRecord */
        $model = $this->owner;

        foreach ($this->attributes as $attr => $options) {

            $this->ensureAttribute($attr, $options);

            if ($file = UploadedFile::getInstance($model, $attr)) {

                $this->createDirByAttr($attr);
                $this->deleteFiles($attr);

                $fileName = $this->getFileName($file);

                if ($this->needCrop($attr)) {
                    $coords = $this->getCoords($attr);
                    if ($coords === false) {
                        throw new InvalidCallException();
                    }
                    $image = $this->crop($file, $coords, $options);
                } else {
                    $image = $this->processImage($file->tempName, $options);
                }

                $image->save($this->getSavePath($attr) . $fileName);
                $model->{$attr} = $fileName;

                if ($this->issetThumbnails($attr)) {

                    $thumbPath =  $this->getThumbPath();
                    $thumbnails = $this->attributes[$attr]['thumbnails'];

                    $this->createDirectory($thumbPath);

                    foreach ($thumbnails as $tmb => $options) {
                        $this->ensureAttribute($tmb, $options);
                        $tmbFileName  = $tmb . '_' . $fileName;
                        $image = $this->processImage($file->tempName, $options);
                        $image->save($thumbPath . $tmbFileName);
                    }

                }

            } elseif (isset($model->oldAttributes[$attr])) {
                $model->{$attr} = $model->oldAttributes[$attr];
            }
        }
    }

    /**
     * @param string $attr name of attribute
     */
    private function createDirByAttr($attr)
    {
        $dir = $this->getSavePath($attr);
        $this->createDirectory($dir);
    }

    /**
     * @param string $attr name of attribute
     * @return string save path
     */
    private function getSavePath($attr = null)
    {
        if (isset($this->attributes[$attr]['savePathAlias'])) {
            return rtrim(Yii::getAlias($this->attributes[$attr]['savePathAlias']), '\/') . DIRECTORY_SEPARATOR;
        }
        if (isset($this->savePathAlias)) {
            return rtrim(Yii::getAlias($this->savePathAlias), '\/') . DIRECTORY_SEPARATOR;
        }

        if (isset(Yii::$aliases['@frontend'])) {
            return Yii::getAlias('@frontend/web/images/' . $this->getShortClassName($this->owner)) . DIRECTORY_SEPARATOR;
        }

        return Yii::getAlias('@app/web/images/' . $this->getShortClassName($this->owner)) . DIRECTORY_SEPARATOR;
    }

    /**
     * @param ActiveRecord $object
     * @return string
     */
    private function getShortClassName($object)
    {
        $object = new \ReflectionClass($object);
        return mb_strtolower($object->getShortName());
    }

    /**
     * Delete images
     * @param string $attr name of attribute
     */
    private function deleteFiles($attr)
    {
        $path = $this->getSavePath($attr);

        if (isset($this->deleteFileName)) {
            $fileName = $this->deleteFileName;
        } else {
            $fileName = $this->owner->{$attr};
        }

        $file = $path . $fileName;
        $this->removeFile($file);

        if ($this->issetThumbnails($attr)) {
            foreach ($this->attributes[$attr]['thumbnails'] as $tmb => $options) {
                $this->ensureAttribute($tmb, $options);
                $file = $path . $tmb . '_' . $fileName;
                $this->removeFile($file);
            }
        }
    }

    /**
     * @param string $attr name of attribute
     * @return bool isset thumbnails or not
     */
    private function issetThumbnails($attr)
    {
        return isset($this->attributes[$attr]['thumbnails']) && is_array($this->attributes[$attr]['thumbnails']);
    }

    /**
     * Получение имени файла. По умолчанию уникальный ID
     * @param $file
     * @return string
     */
    private function getFileName($file)
    {
        if (isset($this->fileName)) {
            return $this->fileName . '.' . $file->extension;
        }

        return uniqid() . '.' . $file->extension;
    }

    /**
     * @param string $attr name of attribute
     * @return bool nedd crop or not
     */
    public function needCrop($attr)
    {
        return isset($this->attributes[$attr]['crop']) ? $this->attributes[$attr]['crop'] : $this->crop;
    }

    /**
     * @param string $attr name of attribute
     * @return array|bool false if no coords and array if coords exists
     */
    private function getCoords($attr)
    {
        $post = Yii::$app->request->post($this->owner->formName());
        if ($post === null) {
            return false;
        }
        $x = $post[$attr . '-coords']['x'];
        $y = $post[$attr . '-coords']['y'];
        $w = $post[$attr . '-coords']['w'];
        $h = $post[$attr . '-coords']['h'];
        if (!isset($x, $y, $w, $h)) {
            return false;
        }

        return [
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h
        ];
    }

    /**
     * Crop image
     * @param UploadedFile $file
     * @param array $coords
     * @param array $options
     * @return \Imagine\Image\ManipulatorInterface
     */
    private function crop($file, array $coords, array $options)
    {
        if (isset($options['width']) && !isset($options['height'])) {
            $width = $options['width'];
            $height = $options['width'] * $coords['h'] / $coords['w'];
        } elseif (!isset($options['width']) && isset($options['height'])) {
            $width = $options['height'] * $coords['w'] / $coords['h'];
            $height = $options['height'];
        } elseif (isset($options['width']) && isset($options['height'])) {
            $width = $options['width'];
            $height = $options['height'];
        } else {
            $width = $coords['w'];
            $height = $coords['h'];
        }

        return Image::crop($file->tempName, $coords['w'], $coords['h'], [$coords['x'], $coords['y']])
            ->resize(new Box($width, $height));
    }

    /**
     * @param string $original path to original image
     * @param array $options with width and height
     * @return \Imagine\Image\ImageInterface
     */
    private function processImage($original, $options)
    {
        list($imageWidth, $imageHeight) = getimagesize($original);
        $image = Image::getImagine()->open($original);
        if (isset($options['width']) && !isset($options['height'])) {
            $width = $options['width'];
            $height = $options['width'] * $imageHeight / $imageWidth;
            $image->resize(new Box($width, $height));
        } elseif (!isset($options['width']) && isset($options['height'])) {
            $width = $options['height'] * $imageWidth / $imageHeight;
            $height = $options['height'];
            $image->resize(new Box($width, $height));
        } elseif (isset($options['width']) && isset($options['height'])) {
            $width = $options['width'];
            $height = $options['height'];
            if ($width / $height > $imageWidth / $imageHeight) {
                $resizeHeight = $width * $imageHeight / $imageWidth;
                $image->resize(new Box($width, $resizeHeight))
                    ->crop(new Point(0, ($resizeHeight - $height) / 2), new Box($width, $height));
            } else {
                $resizeWidth = $height * $imageWidth / $imageHeight;
                $image->resize(new Box($resizeWidth, $height))
                    ->crop(new Point(($resizeWidth - $width) / 2, 0), new Box($width, $height));
            }
        }

        return $image;
    }

    /**
     * function for EVENT_BEFORE_DELETE
     */
    public function beforeDelete()
    {
        foreach ($this->attributes as $attr => $options) {
            $this->ensureAttribute($attr, $options);
            $this->deleteFiles($attr);
        }
    }

    /**
     * @param string $attr name of attribute
     * @param bool|string $tmb false or name of thumbnail
     * @param ActiveRecord $object that keep attrribute. Default $this->owner
     * @return string url to image
     */
    public function getImageUrl($attr, $tmb = false, $object = null)
    {
        $this->checkAttrExists($attr);
        $prefix = $this->getUrlPrefix($attr, $tmb, $object);
        $object = isset($object) ? $object : $this->owner;
        $image = $tmb ? $tmb . DIRECTORY_SEPARATOR . $object->{$attr} : $object->{$attr};
        $file = $this->getSavePath($attr) . $image;
        //Если файл не существует
        if (!file_exists($file)) {
            return null;
        }

        return $prefix . $image;
    }

    /**
     * Check, isset attribute or not
     * @param string $attribute name of attribute
     * @throws InvalidParamException
     */
    private function checkAttrExists($attribute)
    {
        foreach ($this->attributes as $attr => $options) {
            $this->ensureAttribute($attr, $options);
            if ($attr == $attribute) {
                return;
            }
        }
        throw new InvalidParamException();
    }

    /**
     * @param string $attr name of attribute
     * @param bool|string $tmb name of thumbnail
     * @param ActiveRecord $object for default prefix
     * @return string url prefix
     */
    private function getUrlPrefix($attr, $tmb = false, $object = null)
    {
        if ($tmb !== false) {
            if (isset($this->thumbUrl)) {
                return '/' . trim($this->thumbUrl, '/') . '/';
            }
            if (isset($this->attributes[$attr]['thumbnails'][$tmb]['urlPrefix'])) {
                return '/' . trim($this->attributes[$attr]['thumbnails'][$tmb]['urlPrefix'], '/') . '/';
            }
        }

        if (isset($this->attributes[$attr]['urlPrefix'])) {
            return '/' . trim($this->attributes[$attr]['urlPrefix'], '/') . '/';
        }
        if (isset($this->urlPrefix)) {
            return '/' . trim($this->urlPrefix, '/') . '/';
        }

        $object = isset($object) ? $object : $this->owner;

        return Yii::$app->request->baseUrl . '/images/' . $this->getShortClassName($object) . '/';
    }

    /**
     * @return string Путь сохранения миниатюр
     * @throws \yii\base\InvalidArgumentException
     */
    public function getThumbPath()
    {
        if (isset($this->thumbPath)) {
            return rtrim(Yii::getAlias($this->thumbPath), '\/') . DIRECTORY_SEPARATOR;
        }

        return $this->savePath();
    }

    /**
     * Создает папку
     * @param $dir
     * @throws \yii\base\Exception
     */
    private function createDirectory($dir): void
    {
        FileHelper::createDirectory($dir);
    }

    /**
     * @param $file
     */
    private function removeFile($file): void
    {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
