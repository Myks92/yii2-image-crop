Yii2 uploadable and cropable image
==================================
Yii2 расширение для загрузки и кропа изображений

[![Latest Version](https://img.shields.io/github/release/maxdancepro/yii2-image-crop.svg?style=flat-square)](https://github.com/maxdancepro/yii2-image-crop/releases)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://github.com/maxdancepro/yii2-image-crop/blob/master/LICENSE.md)
[![Quality Score](https://img.shields.io/scrutinizer/g/maxdancepro/yii2-image-crop.svg?style=flat-square)](https://scrutinizer-ci.com/g/maxdancepro/yii2-image-crop)
[![Code Climate](https://img.shields.io/codeclimate/github/maxdancepro/yii2-image-crop.svg?style=flat-square)](https://codeclimate.com/github/maxdancepro/yii2-image-crop)
[![Total Downloads](https://img.shields.io/packagist/dt/maxdancepro/yii2-image-crop.svg?style=flat-square)](https://packagist.org/packages/maxdancepro/yii2-image-crop)

Установка
------------

Предпочтительно устанавливать расширение через [composer](http://getcomposer.org/download/).

Выполните в консоли

```
php composer.phar require --prefer-dist maxdancepro/yii2-image-crop "*"
```

или добавьте

```
"maxdancepro/yii2-image-crop": "*"
```

в секцию require вашего `composer.json` файла.


Использование
-----

Когда расширение установлено, его можно использовать таким образом:

В вашей модели:
```php
public function behaviors()
{
    return [
        [
            'class' => \maxdancepro\image\Behavior::className(),
            'savePathAlias' => '@web/images/',
            'urlPrefix' => '/images/',
            'crop' => true,
            'attributes' => [
                'avatar' => [
                    'savePathAlias' => '@web/images/avatars/',
                    'urlPrefix' => '/images/avatars/',
                    'width' => 100,
                    'height' => 100,
                ],
                'logo' => [
                    'crop' => false,
                    'thumbnails' => [
                        'mini' => [
                            'width' => 50,
                        ],
                    ],
                ],
            ],
        ],
    //другие поведения
    ];
}
```
Валидацию атрибута необходимо производить как обычно, через метод rules().

В вашем файле вида с формой:
```php
echo $form->field($model, 'avatar')->widget('maxdancepro\image\Widget');
```

Затем, в основном файле вида:
```php
echo Html::img($model->getImageUrl('avatar'));
echo Html::img($model->getImageUrl('logo', 'mini')); //получим url миниатюры под именем 'mini' для атрибута 'logo'
```

Если вы используете Advanced App Template и это поведение находится в backend модели, то вы можете во frontend модель
добавить трейт
```php
use \maxdancepro\image\GetImageUrlTrait
```
и использовать метод getImageUrl() и во frontend модели.
