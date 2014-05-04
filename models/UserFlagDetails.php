<?php

namespace hiltonjanfield\FBAC\models;

class UserFlagDetails extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user_flag_details';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['flag'], 'required'],
            [['flag', 'name'], 'string', 'max' => 50]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
        ];
    }

}
