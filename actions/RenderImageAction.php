<?php

namespace salopot\attach\actions;

use Yii;
use salopot\attach\behaviors\AttachFileBehavior;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;
use yii\web\NotFoundHttpException;


//url rule 'upload/images/<controller:company>/<behavior:\w+><levelPath:(/(\d|[a-f]){2}){0,16}>/<name:(\d|[a-f]){32}>_<type:\w+>.<ext>' => '<controller>/image',
class RenderImageAction extends Action {

    /**
     * @var ActiveRecord
     */
    public $modelClass = null;

    public function init()
    {
        if (!is_a($this->modelClass, BaseActiveRecord::className(), true) ) {
            throw new InvalidConfigException('ModelClass must be a subclass off BaseActiveRecord');
        }
    }


    public function run($name, $behavior, $type) {
        $levelPath = Yii::$app->request->get('levelPath', false);
        if ($levelPath) {
            $dirs = explode('/', trim($levelPath, '/'));
            for($i=0; $i<count($dirs); $i++) {
                if ($dirs[$i] !== substr($name, $i*2, 2) ) {
                    throw new NotFoundHttpException('The requested page does not exist.');
                }
            }
        }

        $baseModel = (new $this->modelClass);
        $baseBehavior = $baseModel->getBehavior($behavior);
        if ($baseBehavior && ($baseBehavior instanceof AttachFileBehavior)) {

            $model = $baseModel::find()
                //for using index
                ->andWhere(['LIKE' , $baseBehavior->attributeName, strtr($name,['%'=>'\%', '_'=>'\_', '\\'=>'\\\\']).'%', false])
                ->one();
            if ($model && $model->getBehavior($behavior)->hasType($type)) {
                return $model->getBehavior($behavior)->render($type);
            }
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }


}