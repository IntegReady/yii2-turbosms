<?php

namespace stern87\turbosms\models;

use Yii;

/**
 * This is the model class for table "turbo_sms_sent".
 *
 * @property integer $id
 * @property string $date_sent
 * @property string $text
 * @property string $phone
 * @property string $status
 */
class TurboSmsSent extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%turbosms_sent}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['date_sent', 'safe'],
            ['text', 'string'],
            ['phone', 'string', 'max' => 16],
            ['status', 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'date_sent' => Yii::t('app', 'Date Sent'),
            'text' => Yii::t('app', 'Text'),
            'phone' => Yii::t('app', 'Phone'),
            'status' => Yii::t('app', 'Status'),
        ];
    }
}
