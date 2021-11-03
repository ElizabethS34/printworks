<?php
/**
 * gatewaybasket module for Craft CMS 3.x
 *
 * Craft CMS module allowing Gateway items to be added to a shopping basket
 *
 * @link      http://scottbrown.me.uk
 * @copyright Copyright (c) 2021 Scott Brown
 */

namespace modules\gatewaybasketmodule\controllers;

use modules\gatewaybasketmodule\GatewaybasketModule;

use Craft;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\View;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;

use yii;
use yii\helpers\VarDumper;
use yii\web\Response;

/**
 * Default Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your module’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Scott Brown
 * @package   GatewaybasketModule
 * @since     1.0.0
 */
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'basket', 'basket-destroy', 'basket-add', 'personalise'];

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our module's index action URL,
     * e.g.: actions/gatewaybasket-module/default
     *
     * @return mixed
     */

    public function actionIndex()
    {
      $productEntry = Entry::find()
        ->section('products')
        ->slug('18-piece-tool-box')
        ->one();

        VarDumper::dump($productEntry);
        die();
    }

    /**
     * show the basket
     */
     public function actionBasket(): Response
     {
         // retrieve the session
         // this is typed a lot. usually i'd have used a private variable and a constructor but for some reason it's giving me errors in this framework
         $session = Craft::$app->session;

         // get the basket
         $basket = $this->basketContents($session);

         // figure out the total if there's anything there
         $total = 0;
         if(!is_null($basket)){
           foreach($basket as $row){
             $total += $row['price'] * $row['quantity'];
           }
         }

         // render a template
         return $this->renderTemplate(
           'types/gatewaybasket-module/basket.twig',
           $variables = [
             'basket' => $basket,
             'total' => $total
           ],
           View::TEMPLATE_MODE_SITE
         );
     }

    /**
     * Remove all items from the basket
     */
    public function actionBasketDestroy()
    {
        // retrieve the session
        $session = Craft::$app->session;

        // remove the basket from it
        $session->remove('basket');

        // say it's been destroyed
        return 'basket destroyed';
    }

    /**
     * Remove an item from the basket
     *
     * @param string $row_id
     * @return redirect
     */
    public function actionBasketRemove($row_id)
    {
      // retrieve the session
      $session = Craft::$app->session;

      // get the basket
      $basket = $this->basketContents($session);

      // give us the basket without the row we want to delete
      $basket = $this->removeElementWithValue($basket, 'row_id', $row_id);

      // set the basket in the session
      $session->set('basket', $basket);

      // redirect to the basket
      return $this->redirect(Craft::$app->getRequest()->referrer);
    }

    /**
     * Add an item to the basket
     */
    public function actionBasketAdd()
    {
      // only allow this to be visited via a POST request
      $this->requirePostRequest();

      // get the data
      $gateway = Craft::$app->request->getBodyParams()['data'];

      $productEntry = Entry::find()
        ->section('product')
        ->sku($gateway['sku'])
        ->one();

      // log the data for now, for debug and getting attributes
      //Craft::error($productEntry, 'gatewaybasket-module');

      // retrieve the session
      $session = Craft::$app->session;

      /*
      // check for the basket
      if($session->has('basket')){
        // there is a basket, let's retrieve it
        $basket = $session->get('basket');
      } else {
        // there is not a basket yet, let's make one
        $basket = [];
      }

      // sort the gateway data into the way we want
      $new_row = [
        // first, create a random string so we can do stuff to the row later if necessary.
        // we could probably do whatever we needed via the array's index but this gives us another way should the indexing change
        'row_id' => StringHelper::UUID(),
        'product_sku' => $gateway['sku'],
        'product_id' => $gateway['extra']['state']['product_id'],
        'product_name' => $productEntry['productTitle'],
        'price' => $productEntry['price'],
        'quantity' => $gateway['quantity'],
        'printjobref' => $gateway['ref'],
        'image' => $gateway['thumbnails'][0]['url']
      ];

      // push the new row to the basket array
      array_push($basket, $new_row);

      // set the basket in the session
      $session->set('basket', $basket);
      */

      // return the url of the basket page. the callback in the js of the personaliser redirects the user
      return getenv('PRIMARY_SITE_URL') . 'product/' . $productEntry['slug'];
    }

    public function actionPersonalise($gateway_id)
    {
      $iframeOrigin = 'https://g3d-app.com';
      $iframeUrl = $iframeOrigin;
      $iframeUrl .= '/s/app/acp3_2/en_GB/';
      $iframeUrl .= getenv('GATEWAY_CONFIG') . '.html';
      $iframeUrl .= '#p=' . $gateway_id;
      $iframeUrl .= '&r=2d-canvas';
      $iframeUrl .= '&a2c=postMessage';
      $iframeUrl .= '&d=' . getenv('GATEWAY_DROPSHIP');
      $iframeUrl .= '&guid=' . getenv('GATEWAY_COMPANY');
      $iframeUrl .= '&pc=';
      $iframeUrl .= '&_usePs=1&_pav=3';

      //echo $iframeUrl;

      // render a template
      return $this->renderTemplate(
        'types/gatewaybasket-module/personaliser.twig',
        $variables = [
          'iframeOrigin' => $iframeOrigin,
          'iframeUrl' => $iframeUrl,
        ],
        View::TEMPLATE_MODE_SITE
      );
    }

    /**
     * a function to output the values of a session in a more readable way
     * it's not being used any longer but it was one of the first things i wrote when building this so i could see how sessions were structured and if it allowed me to push arrays to it
     * this is a private function so can be called from any other function here by using $this->sessionDumper(Craft::$app->session)
     * as it's private, there is no publicly accessible url for this. it can only be called from other functions in this class
     * you must pass a craft session to this for it to work
     * you can retrieve a session by using: Craft::$app->session
     *
     * @param craft\web\Session $session
     * @return string
     */
    private function sessionDumper($session)
    {
      // create an empty output string to concatenate to
      $output = '';

      // get the session passed to the function and iterate over its values
      foreach($session as $key=>$value){
        // make the key stand out a bit
        $output .= '<strong>' . $key . '</strong><br>';

        // some session values can be arrays, so if they are, loop through those...
        if(is_array($value)){
          // ...and tell me it's an array
          $output .= 'value is an array:<br>';
          // also tell me if it's empty
          if(empty($value)){
            $output .= 'value is an empty array<br>';
          }
          // let's loop again, if it's not another bloody array, otherwise forget it
          // i'm making it sound like this is craft's fault... the only thing i've found that nests arrays so much is this basket system...
          if(!is_array($value)){
            foreach($value as $v){
              $output .= $v . '<br>';
            }
          }
        } else {
          // it's not an array, just a value
          $output .= $value;
        }
        // stick a horizontal rule in to keep it seperate
        $output .= '<hr>';
      }

      // return it in a monospaced way
      return '<pre>' . $output . '</pre>';
    }

    /**
     * get the basket
     * this returns an array if there's stuff in it or null if it's empty
     * there's not a lot here but I've made this a private function as we might want to call this from various places or do stuff to the data first
     * i prefer to keep my main 'repond with a web page' functions as clean as i can and try not to have to type the same code over and over again
     *
     * @param craft\web\Session $session
     * @return array|null
     */
    private function basketContents($session)
    {
      return $session->get('basket');
    }

    private function removeElementWithValue($array, $key, $value){
       foreach($array as $subKey => $subArray){
            if($subArray[$key] == $value){
                 unset($array[$subKey]);
            }
       }
       return $array;
     }
}
