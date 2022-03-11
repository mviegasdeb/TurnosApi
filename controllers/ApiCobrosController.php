<?php

namespace app\controllers;

use app\models\User;
use DateTime;
use Yii;
use yii\base\InvalidConfigException;
use yii\filters\AccessControl;
use yii\filters\auth\HttpBasicAuth;
use yii\helpers\Json;
use yii\httpclient\Client;
use yii\httpclient\Exception;
use yii\httpclient\Request;
use yii\rest\Controller;
use yii\web\Cookie;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

class ApiCobrosController extends Controller
{

    const AUTH_USERNAME = 'demo';
    const AUTH_PASSWORD = 'demopsw';
    const END_POINT_URL = 'http://debqclients.debmedia.com/api/';
    const END_POINT_PASS = 'bolsamza';
    const END_POINT_TOKEN = 'e8c688174cfd4ec8ab50220956d738cc';
    const END_POINT_EMAIL = 'api.bolsamza@bolsamza.com';
    const END_POINT_COOKIE_SESSION_NAME = 'PLAY_SESSION';

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
                    'actions' => ['get-actual-turn'],
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
     * @param int $id
     * @return array
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     * @throws NotFoundHttpException
     */
    public function actionGetActualTurn(int $id)
    {
        $user = $this->getUserById($id);
        if (!$user) {
            return [
                'message' => 'No se encontró el usuario en el archivo json',
                'data' => null
            ];
        }

        $actualUser = $this->findActualUser($this->requestWorkers(), $user);
        if (!$actualUser) {
            return [
                'message' => 'No se encontró el usuario solicitado en la consulta workers',
                'data' => null
            ];
        }

        return [
            'message' => array_key_exists('actualTurn', $actualUser) ? 'ActualTurn encontrado' : 'ActualTurn no encontrado',
            'data' => array_key_exists('actualTurn', $actualUser) ? $actualUser['actualTurn']['letter'] . $actualUser['actualTurn']['number'] : null
        ];
    }

    /**
     * @param $id
     * @return array|mixed|null
     * @throws NotFoundHttpException
     */
    private function getUserById($id)
    {
        $jsonFile = @file_get_contents(Yii::getAlias('@webroot/files') . '/usuarios.json');
        if ($jsonFile === FALSE) {
            throw new NotFoundHttpException('No se encontró el archivo json con los usuarios');
        }
        $usuario = array_filter(Json::decode($jsonFile), function ($user) use ($id) {
            return $user['id'] === $id;
        });
        return !empty($usuario) ? array_shift($usuario) : null;
    }

    /**
     * @param $workersData
     * @param $user
     * @return mixed|null
     */
    private function findActualUser($workersData, $user)
    {
        $filterData = array_filter($workersData, function ($item) use ($user) {
            return array_key_exists('actualUser', $item)
                && $item['actualUser']['uUser']['firstName'] === $user['nombre']
                && $item['actualUser']['uUser']['lastName'] === $user['apellido'];
        });
        return !empty($filterData) ? array_shift($filterData) : null;
    }

    /**
     * @return mixed
     * @throws Exception
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     */
    private function requestWorkers()
    {
        $cookie = $this->getAuthCookie();
        $client = $this->getClientRequest($cookie);
        $response = $client
            ->setMethod('GET')
            ->setUrl(self::END_POINT_URL . 'monitor/workers')
            ->send();

        if (!$response->isOk) {
            //regeneramos la cookie y volvemos a peticionar por q a veces da error con la cookie no vencida!
            $this->renewAuthCookie();
            $cookie = $this->getAuthCookie();
            $client = $this->getClientRequest($cookie);
            $response = $client
                ->setMethod('GET')
                ->setUrl(self::END_POINT_URL . 'monitor/workers')
                ->send();
            if (!$response->isOk) {
                //la segunda vez que da error generamos una excepción
                throw new ForbiddenHttpException('Ocurrió un error al procesar la solicitud a la api workers');
            }
        }
        return $response->data;
    }

    /**
     * @return string
     * @throws InvalidConfigException
     * @throws \Exception
     */
    private function getAuthCookie()
    {
        $savedCookie = $this->readCookie();
        if ($savedCookie) {
            $cookieExpiration = new DateTime($savedCookie->expire);
            $dateNow = new DateTime();
            if ($dateNow < $cookieExpiration) {
                return self::END_POINT_COOKIE_SESSION_NAME . "=" . str_replace('@', '%40', $savedCookie->value);
            }
        }
        $newCookie = $this->renewAuthCookie();
        return self::END_POINT_COOKIE_SESSION_NAME . "=" . str_replace('@', '%40', $newCookie->value);
    }

    private function readCookie()
    {
        $savedCookie = @file_get_contents(Yii::getAlias('@runtime' . '/authCookie'));
        return $savedCookie !== FALSE ? Json::decode($savedCookie, false) : null;
    }

    /**
     * @return \Exception|Exception|Cookie|null
     * @throws InvalidConfigException
     */
    private function renewAuthCookie()
    {
        $request = $this->getClientRequest();
        try {
            $response = $request
                ->setMethod('POST')
                ->setUrl(self::END_POINT_URL . 'authenticate')
                ->setData([
                    'email' => self::END_POINT_EMAIL,
                    'password' => self::END_POINT_PASS,
                    'token' => self::END_POINT_TOKEN
                ])
                ->send();
            $newCookie = $response->getCookies()->get(self::END_POINT_COOKIE_SESSION_NAME);
            $this->saveCookie($newCookie);
            return $newCookie;
        } catch (Exception $exception) {
            return $exception;
        }
    }

    /**
     * @return Request
     * @throws InvalidConfigException
     */
    private function getClientRequest($cookies = null)
    {
        return (new Client())
            ->createRequest()
            ->setFormat(Client::FORMAT_JSON)
            ->setHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Cookie' => $cookies
            ]);
    }

    /**
     * @param $cookie
     */
    private function saveCookie($cookie)
    {
        file_put_contents(Yii::getAlias('@runtime') . '/authCookie', Json::encode($cookie));
    }

}