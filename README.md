Model attach image behavior
===========================
Provides behaviors for attach files to model and manipulate it if files is image

## Features

- Multilevel file store structure
- Can generate thumbnail manual, by demand, after upload or return image as base64 encoded string
- Automatic delete linked file when delete model
- Allow use any image manipulation component. Default use [imagine](https://github.com/avalanche123/Imagine)

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist salopot/yii2-attach-image "dev-master"
```

or add

```
"salopot/yii2-attach-image": "dev-master"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
//in model

use salopot\attach\behaviors\AttachFileBehavior;
use salopot\attach\behaviors\AttachImageBehavior;
...

public function rules()
    {
        return [
            ...
            [['origin_name'], 'string', 'max' => 255],
            [['image'], 'image'],
        ];
    }

public function behaviors()
    {
        return [

            'image' => [
                'class' => AttachImageBehavior::className(),
                'attributeName' => 'image',
                //'relativeTypeDir' => '/upload/images/test/',
                'types' => array(
                    'thumb' => array(
                        //'format' => 'gif', //"gif", "jpeg", "png", "wbmp", "xbm"
                        'process' => function($behavior, $image) {
                            return $image->thumbnail(new \Imagine\Image\Box(150, 150));
                        }
                    ),
                    'background' => array(
                        'process' => function($behavior, $image) {
                            $image = $image->thumbnail(new \Imagine\Image\Box(150, 150));
                            $image->effects()->grayscale();
                            return $image;
                        },
                    ),

                    'main' => array(
                        //'processOn' => AttachImageBehavior::PT_DEMAND, //PT_RENDER, PT_BASE64_ENCODED,
                        'process' => function($behavior, $image) {
                            $watermark = \yii\imagine\Image::getImagine()->open(Yii::$app->params['watermark']);
                            $size = $image->getSize();
                            $wSize = $watermark->getSize();
                            $bottomRight = new \Imagine\Image\Point($size->getWidth() - $wSize->getWidth(), $size->getHeight() - $wSize->getHeight());
                            $image->paste($watermark, $bottomRight);
                            $image = $image->thumbnail(new \Imagine\Image\Box(150, 150));
                            return $image;
                        },
                    ),

                ),
            ]
        ];
    }

//Also can store extended info

    public function init()
        {
            parent::init();
            $this->on(AttachFileBehavior::EVENT_AFTER_ATTACH_DATA, [$this, 'afterAttachData']);
        }

        public function afterAttachData($event) {
            $this->origin_name = $event->uploadedFile->name;
        }

//in form edit view:
<?= $form->field($model, 'image')->fileInput() ?>

//in view:
<?= Html::img($model->getBehavior('image')->getUrl('thumb')); ?>
<?= Html::img($model->getBehavior('image')->getUrl('background')); ?>
<?= Html::img($model->getBehavior('image')->getUrl('main')); ?>