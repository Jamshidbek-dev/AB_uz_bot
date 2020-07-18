<?php
// отсекаем лишние запросы
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo "Hello world!";
    exit();
}

// запускаем
new Yandex();

/**
 * Class Yandex
 */
class Yandex
{
    private $data;

    // первичные данные
    private $token = "1234112864:AAERBqV5d8xqiLDc4aB3rFypsdbNCvIaMVU";
    private $admin = 640892021;

    // для соединения с БД
    private $host = 'localhost';
    private $db = 'cp55334_tezovqat';
    private $user = 'cp55334_tezovqat';
    private $pass = '18051992';
    private $charset = 'utf8mb4';

    /**
     * @var PDO
     */
    private $pdo;

    public function __construct()
    {
        $this->data = $_POST;
        $this->setPdo();
        $this->getPay();
    }

    /** Получаем Лабел
     * @return mixed
     */
    private function getLabel()
    {
        return $this->data['label'];
    }

    /** Получаем id пользователя
     * @return mixed
     */
    private function getIdUser()
    {
        return explode(":", $this->getLabel())[0];
    }

    /** Получаем id заказа
     * @return mixed
     */
    private function getIdOrder()
    {
        return explode(":", $this->getLabel())[1];
    }

    /** Получаем сумму
     * @return mixed
     */
    private function getFullSum()
    {
        return $this->data['withdraw_amount'];
    }

    /**
     *  Принимаем оплату
     */
    private function getPay()
    {
        // делаем запрос в бд по пришедшим данным
        $order = $this->pdo->prepare("SELECT * FROM bot_shop_order WHERE user_id = :user_id AND id = :order_id AND status = 0");
        $order->execute(['user_id' => $this->getIdUser(), 'order_id' => $this->getIdOrder()]);
        // если запись в бд найдена
        if ($order->rowCount() > 0) {
            // запрос на обновление статуса заказа
            $update = $this->pdo->prepare("UPDATE bot_shop_order SET status = 1 WHERE id = :id");
            // если обновили то уведомляем
            if ($update->execute(['id' => $this->getIdOrder()])) {
                // шлем пользователю уведомление о заказ оплачен
                $this->sendMsg(['chat_id' => $this->getIdUser(), 'text' => "Заказ #" . $this->getIdOrder() . " успешно оплачен. В ближайшее время с вами свяжется менеджер."]);
                // шлем алмину уведомление о заказ оплачен
                $this->sendMsg(['chat_id' => $this->getIdUser(), 'text' => "Заказ #" . $this->getIdOrder() . " успешно оплачен. Сумма " . $this->getFullSum() . "."]);
            } else {
                // шлем пользователю уведомление о том что пришли деньги за заказ, но есть проблемы
                $this->sendMsg(['chat_id' => $this->getIdUser(), 'text' => 'Пришли деньги ' . $this->getFullSum() . ' РУБ, за заказ ' . $this->getIdOrder() . ' но не удалось обновить статус заказа. С вами свяжется менеджер.']);
                // шлем админу уведомление о том что пришли деньги за заказ, но есть проблемы
                $this->sendMsg(['chat_id' => $this->admin, 'text' => 'Пришли деньги ' . $this->getFullSum() . ' РУБ, за заказ ' . $this->getIdOrder() . ' от пользователя ' . $this->getIdUser() . ' но не удалось обновить статус заказа.']);
            }
        } else {
            // шлем админу уведомление о том что пришли какие-то деньги
            $this->sendMsg(['chat_id' => $this->admin, 'text' => 'Пришли деньги ' . $this->getFullSum() . ' руб, но назначение не понятно ']);
        }
    }

    /** Отправляем сообщение
     * @param $fields
     * @return mixed
     */
    public function sendMsg($fields)
    {
        $ch = curl_init('https://api.telegram.org/bot' . $this->token . '/sendMessage');
        curl_setopt_array($ch, array(
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 10
        ));
        $r = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $r;
    }

    /**
     *  Соединение с базой
     */
    private function setPdo()
    {
        $dsn = "mysql:host=$this->host;dbname=$this->db;charset=$this->charset";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        $this->pdo = new PDO($dsn, $this->user, $this->pass, $opt);
    }
}
