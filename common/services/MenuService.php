<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2020-01-22 10:38
 */

namespace common\services;

use backend\models\search\MenuSearch;
use Yii;
use common\helpers\FamilyTree;
use common\helpers\FileDependencyHelper;
use common\models\Menu;
use yii\caching\FileDependency;
use yii\helpers\ArrayHelper;

class MenuService extends Service  implements MenuServiceInterface
{

    public function getSearchModel(array $query, array $options=[])
    {
        return new MenuSearch();
    }

    public function getModel($id, array $options = [])
    {
        return Menu::findOne($id);
    }

    public function getNewModel(array $options = [])
    {
        return new Menu();
    }

    /**
     * get authorized backend menus by admin user id
     *
     * @param $userId
     * @return array|mixed|\yii\db\ActiveRecord[]
     * @throws \yii\base\InvalidConfigException
     */
    public function getAuthorizedBackendMenusByUserId($userId)
    {
        $menus = $this->getMenus(Menu::TYPE_BACKEND, Menu::DISPLAY_YES);
        $permissions = Yii::$app->getAuthManager()->getPermissionsByUser($userId);
        $permissions = array_keys($permissions);
        if (in_array(Yii::$app->getUser()->getId(), Yii::$app->getBehavior('access')->superAdminUserIds)) {
            return $menus;//config user ids own all permissions
        }

        $tempMenus = [];
        foreach ($menus as $menu) {
            /** @var self $menu */
            $url = $menu->url;
            $temp = @json_decode($menu->url, true);
            if ($temp !== null) {
                $url = $temp[0];
            }
            if (strpos($url, '/') !== 0) $url = '/' . $url;
            $url = $url . ':GET';
            if (in_array($url, $permissions)) {
                $menu = $this->getAncestorMenusById($menu->id) + [$menu];
                $tempMenus = array_merge($tempMenus, $menu);
            }
        }

        $hasPermissionMenus = [];
        foreach ($tempMenus as $v) {
            $hasPermissionMenus[] = $v;
        }
        ArrayHelper::multisort($hasPermissionMenus, 'sort', SORT_ASC);
        return $hasPermissionMenus;
    }

    /**
     * set menu name with prefix level characters
     *
     * @param $menus
     * @return array
     */
    public function setMenuNameWithPrefixLevelCharacters($menus)
    {
        foreach ($menus as $k => $menu) {
            /** @var Menu $menu */
            if (isset($menus[$k + 1]['level']) && $menus[$k + 1]['level'] == $menu['level']) {
                $name = ' ├' . $menu['name'];
            } else {
                $name = ' └' . $menu['name'];
            }
            if (end($menus)->id == $menu->id) {
                $sign = ' └';
            } else {
                $sign = ' │';
            }
            $menu->name = str_repeat($sign, $menu['level'] - 1) . $name;
        }
        return ArrayHelper::index($menus, 'id');
    }


    /**
     * get ancestor menus by menu id
     *
     * @param $id
     * @param $menuType
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function getAncestorMenusById($id, $menuType)
    {
        $menus = $this->getMenus($menuType);
        $familyTree = new FamilyTree($menus);
        return $familyTree->getAncectors($id);
    }

    /**
     * get descendant menus by menu id
     *
     * @param $id
     * @param $menuType
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function getDescendantMenusById($id, $menuType)
    {
        $familyTree = new FamilyTree($this->getMenus($menuType));
        return $familyTree->getDescendants($id);
    }

    /**
     * get menus from cache, if cache not exist then get from storage and set to cache
     *
     * @param $menuType
     * @param $isDisplay
     * @return array|mixed|\yii\db\ActiveRecord[]
     * @throws \yii\base\InvalidConfigException
     */
    public function getMenus($menuType=null, $isDisplay=null){
        $cacheKey = "menu_" . (string)$menuType . "_" . (string)$isDisplay;
        //echo $cacheKey;exit;
        $cache = Yii::$app->getCache();
        $menus = $cache->get($cacheKey);
        if( $menus === false ){
            $cacheDependencyObject = Yii::createObject([
                'class' => FileDependencyHelper::className(),
                'fileName' => Menu::MENU_CACHE_DEPENDENCY_FILE,
            ]);
            $dependency = [
                'class' => FileDependency::className(),
                'fileName' => $cacheDependencyObject->createFileIfNotExists(),
            ];
            $menus = $this->getMenusFromStorage($menuType, $isDisplay);
            if ( $cache->set($cacheKey, 1, 60*60, Yii::createObject($dependency)) === false ){
                Yii::error(__METHOD__ . " save menu cache error");
            }
        }
        return $menus;
    }

    /**
     * get menus from storage
     *
     * @param $menuType
     * @param $isDisplay
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getMenusFromStorage($menuType=null, $isDisplay=null){
        return Menu::getMenus($menuType, $isDisplay);
    }

    /**
     * get menus name
     *
     * @param int $menuType
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function getMenusNameWithPrefixLevelCharacters($menuType)
    {
        $menus = $this->getDescendantMenusById(0, $menuType);
        $menus = $this->setMenuNameWithPrefixLevelCharacters($menus);
        $new = [];
       foreach ($menus as $menu){
           $new[$menu->id] = $menu->name;
       }
       return $new;
    }
}