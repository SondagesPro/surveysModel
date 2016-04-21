<?php
/**
 * surveysModel : allow to use some survey for model : user can use survey model (copy), some other can manage them
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2016 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 0.0.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class surveysModel extends PluginBase {
    protected $storage = 'DbStorage';
    static protected $description = 'Allow to set survey to model : user can have read/copy access to this specific survey, or can manage it';
    static protected $name = 'surveysModel';

    /**
    * Add function to be used in newDirectRequest/newUnsecureRequest event
    */
    public function init()
    {
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        // To add some script
        $this->subscribe('afterPluginLoad','addNeededJs');
        // The script need some PHP , then doing it in ajax
        $this->subscribe('newDirectRequest');
        // Fixe Permission when happen
        $this->subscribe('beforeHasPermission');

    }
    protected $settings = array(
        'managers' => array(
            'type' => 'select',
            'label'=> "Manager can manage survey model.",
            'htmlOptions'=>array(
                'multiple'=>'multiple',
            ),
            'options' => array(1), // This broke if there are no user
            'default'=> null,
            'help'=> "Super admin have always this right."
        ),
        'users' => array(
            'type' => 'select',
            'label'=> "User can use (copy and view) survey model",
            'htmlOptions'=>array(
                'multiple'=>'multiple',
            ),
            'options' => array(1), // This broke if there are no user
            'default'=> null
        ),
    );

    /**
     * Get the survey setting for this survey
     * Add setting only if user have the right to update
     */
    public function beforeSurveySettings()
    {
        $iUserId = Yii::app()->session['loginID'];
        $aSettings=$this->getPluginSettings(true);

        if(in_array($iUserId,$aSettings['managers']['current']))
        {
            $this->event->set("surveysettings.{$this->id}", array(
                'name' => get_class($this),
                'settings' => array(
                    'ismodel'=>array(
                        'label'=>"This survey is a model",
                        'type'=>'boolean',
                        'current' => $this->get('ismodel', 'Survey', $this->event->get('survey'),0)),
                    ),
                )
            );
        }
    }

    /**
     * Save survey setting
     * Control if survey admin have right to upadte "model" system
     */
    public function newSurveySettings()
    {
        $iUserId = Yii::app()->session['loginID'];
        $aSettings=$this->getPluginSettings(true);
        if(in_array($iUserId,$aSettings['managers']['current']) && !empty($this->getEvent()->get('settings')))
        {
            tracevar($this->getEvent()->get('settings'));
            foreach ($this->getEvent()->get('settings') as $name => $value)
            {
                $this->set($name, $value, 'Survey', $this->getEvent()->get('survey'));
            }
        }
    }

    /**
    * If survey is master : allow copying / updating according to Permission
    */
    public function beforeHasPermission()
    {
        if($this->event->get('sEntityName')!="Survey"){
            return; // Go after only if it Survey
        }
        $iSurveyId=$this->event->get('iEntityID');
        $oIsModel=PluginSetting::model()->find(
            "plugin_id=:plugin_id AND model=:model  AND ".Yii::app()->db->quoteColumnName("key")."=:setting AND ".Yii::app()->db->quoteColumnName("value")." LIKE :value",
            array(":plugin_id"=>$this->id,":model"=>"Survey",":setting"=>'ismodel',":value"=>"%1%")
        );
        if(!$oIsModel){
            return; // Not a model
        }
        // Ok, then go go !
        $sCRUD=$this->event->get('sCRUD');
        $iUserID=$this->event->get('iUserID');
        if(is_null($iUserID))
        {
            $iUserID=Yii::app()->session['loginID'];
        }
        $aSettings=$this->getPluginSettings(true);
        if(in_array($sCRUD,array('read','export')))
        {
            if(in_array($iUserId,$aSettings['managers']['current']) || in_array($iUserId,$aSettings['users']['current']))
            {
                $this->event->set('bPermission',true);
            }
        }
        elseif(in_array($sCRUD,array('create','update','delete','import')))
        {
            if(in_array($iUserId,$aSettings['managers']['current']))
            {
                $this->event->set('bPermission',true);
            }
        }
    }
    /**
     * Add needed js if needed
     */
    public function newDirectRequest()
    {
        $oEvent = $this->event;
        $sAction=$oEvent->get('function');
        if ($oEvent->get('target') != self::$name)
            return;
        // Return only if user have master or read rights
        $iUserId = Yii::app()->session['loginID'];
        $aSettings=$this->getPluginSettings(true);
        if(in_array($iUserId,$aSettings['managers']['current']) || in_array($iUserId,$aSettings['users']['current']))
        {
            $this->getMasterSurvey();
        }
    }

    /**
     * Answer to ajax request
     */
    public function addNeededJs()
    {
        //$sController=Yii::app()->getController()->getId();
        $oController=Yii::app()->getController();
        if($oController && $oController->getId()=="admin")
        {
            $aOptionJson=array(
                'jsonUrl'=>$this->api->createUrl('plugins/direct', array('plugin' => get_class($this),'function' => 'auto')),
            );
            App()->clientScript->registerScript('surveysModel',"surveysModel = ".json_encode($aOptionJson).";\n",CClientScript::POS_HEAD);
            $assetUrl=Yii::app()->assetManager->publish(dirname(__FILE__)."/assets/");
            App()->clientScript->registerScriptFile(Yii::app()->assetManager->publish(dirname(__FILE__)."/assets/surveysmodel.js"));
        }

    }
    /**
     * Get the needed master survey
     */
    public function getMasterSurvey()
    {
        $oSurveysMasters=PluginSetting::model()->findAll(
            "plugin_id=:plugin_id AND model=:model  AND ".Yii::app()->db->quoteColumnName("key")."=:setting AND ".Yii::app()->db->quoteColumnName("value")." LIKE :value",
            array(":plugin_id"=>$this->id,":model"=>"Survey",":setting"=>'ismodel',":value"=>"%1%")
        );
        $aSurveyMasters=array();
        if(!empty($oSurveysMasters))
        {
            foreach($oSurveysMasters as $oSurveysMaster)
            {
                $oSurvey=Survey::model()->with('defaultlanguage')->findByPk($oSurveysMaster->model_id);
                if($oSurvey)
                {
                    $aSurveyMasters[]=array(
                        "value"=>$oSurveysMaster->model_id,
                        "label"=>$oSurvey->defaultlanguage->surveyls_title,
                    );
                }
            }
        }
        $this->displayJson($aSurveyMasters);
    }
    /**
     * Get the plugin settings
     * We need to set list of users when we go to settings
     * @param boolean $getValuee : get actual value
     */
    public function getPluginSettings($getValues=true)
    {

        $pluginSettings=parent::getPluginSettings($getValues);

        $oUsers = User::model()->findAll();
        $aPotentialUsers=array();
        foreach($oUsers as $oUser)
        {
            if(!Permission::model()->hasGlobalPermission("superadmin",'read',$oUser->uid))
            {
                $aPotentialUsers[]=$oUser;
            }
            elseif($getValues && !in_array($oUser->uid,$pluginSettings['managers']['current']))
            {
                $pluginSettings['managers']['current'][]=$oUser->uid;
            }
        }

        // need to be : super admin to manage this ?
        $pluginSettings['managers']['options']=CHtml::listData($oUsers,'uid',
                    function($model){
                        return $model->full_name.' ('.$model->users_name.')';
                    }
        );
        if(!empty($aPotentialUsers))
        {
            $pluginSettings['users']['options']=CHtml::listData($aPotentialUsers,'uid',
                        function($model){
                            return $model->full_name.' ('.$model->users_name.')';
                        }
            );
        }
        else
        {
            $pluginSettings['users']['type']='info';
            $pluginSettings['users']['content']='<p class="alert alert-info">All of your users are super-admin.</p>';
        }

        return $pluginSettings;
    }

    /**
     * render an object in json
     */
    private function displayJson($object)
    {
        if(App()->getConfig("debug")<2)
        {
            Yii::import('application.helpers.viewHelper');
            viewHelper::disableHtmlLogging();
        }
        if(App()->getConfig("debug")<=2)
        {
            header('Content-type: application/json');
        }
        echo json_encode($object);
        Yii::app()->end();
    }

}
