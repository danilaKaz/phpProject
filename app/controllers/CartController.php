<?php


namespace app\controllers;


use app\models\CartModel;
use app\models\OrderModel;
use app\models\UserModel;

class CartController extends AppController
{
    public function addAction()
    {
        $id = !empty($_GET['id']) ? (int)$_GET['id'] : null;
        $qty = !empty($_GET['qty']) ? (int)$_GET['qty'] : null;
        $mod_id = !empty($_GET['mod']) ? (int)$_GET['mod'] : null;
        $mod = null;
        if ($id) {
            $product = \R::findOne('product', 'id = ?', [$id]);
            if (!$product) {
                return false;
            }
            if ($mod_id) {
                $mod = \R::findOne(
                    'modification',
                    'id = ? AND product_id = ?',
                    [$mod_id, $id]
                );
            }
        }
        $cart = new CartModel();
        $cart->addToCart($product, $qty, $mod);
        if ($this->isAjax()) {
            $this->loadView('cart_model');
        }
        redirect();
    }

    public function showAction()
    {
        $this->loadView('cart_model');
    }

    public function deleteAction()
    {
        $id = !empty($_GET['id']) ? $_GET['id'] : null;
        if (isset($_SESSION['cart'][$id])) {
            $cart = new CartModel();
            $cart->deleteItem($id);
        }
        if ($this->isAjax()) {
            $this->loadView('cart_model');
        }
        redirect();
    }

    public function clearAction()
    {
        unset($_SESSION['cart']);
        unset($_SESSION['cart.qty']);
        unset($_SESSION['cart.sum']);
        unset($_SESSION['cart.currency']);

        $this->loadView('cart_model');
    }

    public function viewAction()
    {
        $this->setMeta('Корзина');
    }

    public function checkoutAction()
    {
        if (!empty($_POST)) {
            //регистрация пользователя
            if (!UserModel::checkAuth()) {
                $user = new UserModel();
                $data = $_POST;
                $user->load($data);
                if (!$user->validate($data) || !$user->checkUnique()) {
                    $user->getErrors();
                    $_SESSION['form-data'] = $data;
                    redirect();
                } else {
                    $user->attributes['password'] = password_hash($user->attributes['password'], PASSWORD_DEFAULT);
                    if (!$user_id = $user->save('user')) {
                        $_SESSION['error'] = 'Ошибка!';
                        redirect();
                    }
                }
            }

            // сохранение заказа
            $data['user_id'] = isset($user_id) ? $user_id :  $_SESSION['user']['id'];
            $data['note'] = !empty($_POST['note']) ? $_POST['note'] : '';
            $user_email = isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : $_POST['email'];
            $order_id = OrderModel::saveOrder($data);

            // оплата

            OrderModel::mailOrder($order_id,$user_email);
        }
        redirect();
    }
}
