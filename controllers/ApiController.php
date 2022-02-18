<?php

namespace app\controllers;

use app\models\User;
use Yii;
use yii\filters\AccessControl;
use yii\filters\auth\HttpBasicAuth;
use yii\rest\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

class ApiController extends Controller
{

    const AUTH_USERNAME = 'demo';
    const AUTH_PASSWORD = 'demopsw';
    public $enableCsrfValidation = false;

    public function init()
    {
        parent::init();
        Yii::$app->user->enableSession = false;
        Yii::$app->user->loginUrl = null;
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => HttpBasicAuth::class,
            'auth' => function ($username, $password) {
                if ($username === self::AUTH_USERNAME && $password === self::AUTH_PASSWORD) {
                    return User::findIdentity(100);
                }
                return null;
            },
        ];
        $behaviors['ContentNegotiator'] = [
            'class' => 'yii\filters\ContentNegotiator',
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];
        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => [
                [
                    'actions' => ['get-movements'],
                    'allow' => true,
                    'roles' => ['@']
                ],
            ],
            'denyCallback' => function () {
                if (!Yii::$app->user->isGuest) {
                    throw new ForbiddenHttpException('No tiene permitido ejecutar esta acción.');
                } else {
                    throw new UnauthorizedHttpException('Debe iniciar sesión.');
                }
            }
        ];
        return $behaviors;
    }

    public function actionGetMovements($date = null, $branch_name = null)
    {
//        $query = "SELECT * FROM qmovements_bolsamza WHERE
//            (action_text IN ('LLAMADA','FINALIZACION'))
//            AND month(action_time) = 10
//            AND day(action_time) = 27
//            AND year(action_time) = 2021";
//        $result = Yii::$app->db->createCommand($query)->queryAll();

        return $branch_name;
    }

}