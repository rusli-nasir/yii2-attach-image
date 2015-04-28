<?php
namespace salopot\attach\base;


use yii\base\Event;

class AfterAttachDataEvent extends Event {
    public $behavior;
    public $uploadedFile;
}