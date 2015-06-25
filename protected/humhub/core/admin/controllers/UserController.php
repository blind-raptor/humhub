<?php

namespace humhub\core\admin\controllers;

use Yii;
use humhub\compat\HForm;
use humhub\components\Controller;
use humhub\core\user\models\User;
use humhub\core\user\models\Group;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;

/**
 * @package humhub.modules_core.admin.controllers
 * @since 0.5
 */
class UserController extends Controller
{

    public $subLayout = "/_layout";

    public function behaviors()
    {
        return [
            'acl' => [
                'class' => \humhub\components\behaviors\AccessControl::className(),
                'adminOnly' => true
            ]
        ];
    }

    /**
     * Returns a List of Users
     */
    public function actionIndex()
    {
        $searchModel = new \humhub\core\admin\models\UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', array(
                    'dataProvider' => $dataProvider,
                    'searchModel' => $searchModel
        ));
    }

    /**
     * Edits a user
     *
     * @return type
     */
    public function actionEdit()
    {
        $user = User::findOne(['id' => Yii::$app->request->get('id')]);

        if ($user == null)
            throw new \yii\web\HttpException(404, Yii::t('AdminModule.controllers_UserController', 'User not found!'));

        $user->scenario = 'editAdmin';
        $user->profile->scenario = 'editAdmin';
        $profile = $user->profile;

        // Build Form Definition
        $definition = array();
        $definition['elements'] = array();
        // Add User Form
        $definition['elements']['User'] = array(
            'type' => 'form',
            'title' => 'Account',
            'elements' => array(
                'username' => array(
                    'type' => 'text',
                    'class' => 'form-control',
                    'maxlength' => 25,
                ),
                'email' => array(
                    'type' => 'text',
                    'class' => 'form-control',
                    'maxlength' => 100,
                ),
                'group_id' => array(
                    'type' => 'dropdownlist',
                    'class' => 'form-control',
                    'items' => \yii\helpers\ArrayHelper::map(Group::find()->all(), 'id', 'name'),
                ),
                'super_admin' => array(
                    'type' => 'checkbox',
                ),
                'auth_mode' => array(
                    'type' => 'dropdownlist',
                    'disabled' => 'disabled',
                    'class' => 'form-control',
                    'items' => array(
                        User::AUTH_MODE_LOCAL => Yii::t('AdminModule.controllers_UserController', 'Local'),
                        User::AUTH_MODE_LDAP => Yii::t('AdminModule.controllers_UserController', 'LDAP'),
                    ),
                ),
                'status' => array(
                    'type' => 'dropdownlist',
                    'class' => 'form-control',
                    'items' => array(
                        User::STATUS_ENABLED => Yii::t('AdminModule.controllers_UserController', 'Enabled'),
                        User::STATUS_DISABLED => Yii::t('AdminModule.controllers_UserController', 'Disabled'),
                        User::STATUS_NEED_APPROVAL => Yii::t('AdminModule.controllers_UserController', 'Unapproved'),
                    ),
                ),
            ),
        );

        // Add Profile Form
        $definition['elements']['Profile'] = array_merge(array('type' => 'form'), $profile->getFormDefinition());

        // Get Form Definition
        $definition['buttons'] = array(
            'save' => array(
                'type' => 'submit',
                'label' => Yii::t('AdminModule.controllers_UserController', 'Save'),
                'class' => 'btn btn-primary',
            ),
            'become' => array(
                'type' => 'submit',
                'label' => Yii::t('AdminModule.controllers_UserController', 'Become this user'),
                'class' => 'btn btn-danger',
            ),
            'delete' => array(
                'type' => 'submit',
                'label' => Yii::t('AdminModule.controllers_UserController', 'Delete'),
                'class' => 'btn btn-danger',
            ),
        );

        $form = new HForm($definition);
        $form->models['User'] = $user;
        $form->models['Profile'] = $profile;

        if ($form->submitted('save') && $form->validate()) {
            if ($form->save()) {
                return $this->redirect(Url::toRoute('/admin/user'));
            }
        }

        // This feature is used primary for testing, maybe remove this in future
        if ($form->submitted('become')) {

            Yii::$app->user->switchIdentity($form->models['User']);
            return $this->redirect(Url::toRoute("/"));
        }

        if ($form->submitted('delete')) {
            return $this->redirect(Url::toRoute(['/admin/user/delete', 'id' => $user->id]));
        }

        return $this->render('edit', array('hForm' => $form));
    }

    public function actionAdd()
    {
        $_POST = Yii::app()->input->stripClean($_POST);

        $userModel = new User('register');
        $userPasswordModel = new UserPassword('newPassword');
        $profileModel = $userModel->profile;
        $profileModel->scenario = 'register';

        // Build Form Definition
        $definition = array();
        $definition['elements'] = array();

        $groupModels = Group::model()->findAll(array('order' => 'name'));
        $defaultUserGroup = HSetting::Get('defaultUserGroup', 'authentication_internal');
        $groupFieldType = "dropdownlist";
        if ($defaultUserGroup != "") {
            $groupFieldType = "hidden";
        } else if (count($groupModels) == 1) {
            $groupFieldType = "hidden";
            $defaultUserGroup = $groupModels[0]->id;
        }

        // Add User Form
        $definition['elements']['User'] = array(
            'type' => 'form',
            'title' => Yii::t('UserModule.controllers_AuthController', 'Account'),
            'elements' => array(
                'username' => array(
                    'type' => 'text',
                    'class' => 'form-control',
                    'maxlength' => 25,
                ),
                'email' => array(
                    'type' => 'text',
                    'class' => 'form-control',
                    'maxlength' => 100,
                ),
                'group_id' => array(
                    'type' => $groupFieldType,
                    'class' => 'form-control',
                    'items' => CHtml::listData($groupModels, 'id', 'name'),
                    'value' => $defaultUserGroup,
                ),
            ),
        );

        // Add User Password Form
        $definition['elements']['UserPassword'] = array(
            'type' => 'form',
            #'title' => 'Password',
            'elements' => array(
                'newPassword' => array(
                    'type' => 'password',
                    'class' => 'form-control',
                    'maxlength' => 255,
                ),
                'newPasswordConfirm' => array(
                    'type' => 'password',
                    'class' => 'form-control',
                    'maxlength' => 255,
                ),
            ),
        );

        // Add Profile Form
        $definition['elements']['Profile'] = array_merge(array('type' => 'form'), $profileModel->getFormDefinition());

        // Get Form Definition
        $definition['buttons'] = array(
            'save' => array(
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'label' => Yii::t('UserModule.controllers_AuthController', 'Create account'),
            ),
        );

        $form = new HForm($definition);
        $form['User']->model = $userModel;
        $form['UserPassword']->model = $userPasswordModel;
        $form['Profile']->model = $profileModel;

        if ($form->submitted('save') && $form->validate()) {

            $this->forcePostRequest();

            $form['User']->model->status = User::STATUS_ENABLED;
            if ($form['User']->model->save()) {
                // Save User Profile
                $form['Profile']->model->user_id = $form['User']->model->id;
                $form['Profile']->model->save();

                // Save User Password
                $form['UserPassword']->model->user_id = $form['User']->model->id;
                $form['UserPassword']->model->setPassword($form['UserPassword']->model->newPassword);
                $form['UserPassword']->model->save();

                $this->redirect($this->createUrl('index'));
                return;
            }
        }

        $this->render('add', array('form' => $form));
    }

    /**
     * Deletes a user permanently
     */
    public function actionDelete()
    {

        $id = (int) Yii::$app->request->get('id');
        $doit = (int) Yii::$app->request->get('doit');


        $user = User::findOne(['id' => $id]);

        if ($user == null) {
            throw new HttpException(404, Yii::t('AdminModule.controllers_UserController', 'User not found!'));
        } elseif (Yii::$app->user->id == $id) {
            throw new HttpException(400, Yii::t('AdminModule.controllers_UserController', 'You cannot delete yourself!'));
        }

        if ($doit == 2) {

            $this->forcePostRequest();

            foreach (\humhub\core\space\models\Membership::GetUserSpaces() as $workspace) {
                if ($workspace->isSpaceOwner($user->id)) {
                    $workspace->addMember(Yii::$app->user->id);
                    $workspace->setSpaceOwner(Yii::$app->user->id);
                }
            }
            $user->delete();
            return $this->redirect(Url::to(['/admin/user']));
        }

        return $this->render('delete', array('model' => $user));
    }

}
