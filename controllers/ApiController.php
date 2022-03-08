<?php

namespace app\controllers;

use app\models\User;
use HttpInvalidParamException;
use Yii;
use yii\db\DataReader;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\filters\auth\HttpBasicAuth;
use yii\rest\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

class ApiController extends Controller
{

    public $defaultAction = 'get-movements';
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

    /**
     * @param $date
     * @param null $branch_name
     * @return array|DataReader
     * @throws NotFoundHttpException
     */
    public function actionGetMovements($date, $branch_name = null)
    {
        try {
            $params = [];
            $params[':p0'] = $date;

            $query = "SELECT * FROM qmovements_bolsamza WHERE
            (action_text IN ('LLAMADA','FINALIZACION'))
            AND CONVERT(DATE, action_time) = :p0";

            if ($branch_name) {
                $params[':p1'] = $branch_name;
                $query .= ' AND branch_name = :p1';
            }
            $result = Yii::$app->db->createCommand($query, $params)->queryAll();
        } catch (Exception $exception) {
            throw  new NotFoundHttpException('Ocurrió un error al ejecutar la consulta en la base de datos.'.$exception->getMessage());
        }
        return $result;
    }

}