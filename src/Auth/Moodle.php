<?php


namespace Glfs\Auth;


use app\models\forms\LoginForm;
use app\models\interfaces\InternalAuth;
use app\modules\student\models\students\Students;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\console\Response;

/**
 * Class Moodle
 * @package app\custom\models\auth
 *
 * Авторизация с помощью внешнего сервера Moodle
 */
class Moodle extends InternalAuth
{
    public $name = 'Moodle';
    public $settings = [
        'moodle_auth' => [
            "name" => "Ссылка на внешний аутенфикатор Moodle",
            'placeholder' => 'https://example.moodle/api/auth.php',
            "type" => "string",
        ],
    ];


    /**
     * @param $login
     * @param $password
     * @param bool $ajax
     * @return array|false|Response|\yii\web\Response
     * @throws GuzzleException
     */
    public function authenticate($login, $password, bool $ajax = false)
    {
        try {
            $client = new Client();
            $res = $client->request('POST', $this->getValue('moodle_auth'), [
                'form_params' => [
                    'username' => $login,
                    'password' => $password,
                ]
            ]);

            if ($res->getStatusCode() === 200) {
                $auth = json_decode($res->getBody()->getContents());

                $student = Students::findAll([
                    'family_name' => $auth->user->lastname,
                    'name' => $auth->user->firstname,
                    'surname' => $auth->user->middlename,
                ]);

                if (count($student) > 1) {
                    $this->error = 'Найдено более 1 учетной записи, обратитесь к Администратору';
                    return false;
                }
                if (!isset($student[0]->user)) {
                    $this->error = 'Учетная запись отсутствует либо не активирована! Обратитесь к Администратору!';
                    return false;
                }

                if ($ajax) {
                    return [
                        'login' => $student[0]->user->login,
                        'name' => $student[0]->user->name,
                    ];
                }
                /** Костыль! */
                \Yii::$app->user->login($student[0]->user, true ? LoginForm::$sessionDuration : 0);
                return \Yii::$app->response->redirect('/'); //Возможно бесконечный цикл?
            }
        }  catch (\Exception $e) {}

        return false;
    }

    public function test(): bool
    {
        return true;
    }
}
