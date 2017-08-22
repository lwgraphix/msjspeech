<?php

namespace App\Menu;

//        self::addMultilevelMenuItem('Кошельки', '/wallet', 'money', array(
//            array('name' => 'Привязка ферм', 'route' => '/wallet/rig', 'icon' => 'link', 'globalUrl' => false, 'active' => false),
//        ));

use App\Type\UserType;

class MenuBuilder
{
    private static $menu;

    public static function userMenu()
    {
        self::addMenuItem('Profile', '/user/profile', 'user');
        self::addMenuItem('Balance', '/user/balance', 'money');

        return self::generateHTML('', 'Manage');
    }

    public static function adminMenu($role)
    {
        self::addMenuItem('Edit registration form', '/admin/signup/edit', 'user-plus');
        self::addMenuItem('Users list', '/admin/users/list', 'list-ol');
        self::addMenuItem('System settings', '/admin/settings', 'cog');

        return self::generateHTML('', 'Administration');
    }

    public static function pagesMenu()
    {
        self::addMenuItem('Page menu item', '/user/profile', 'user');

        return self::generateHTML('', 'Pages');
    }

    public static function build($role) {
        $menu = null;

        if ($role == UserType::ADMINISTRATOR || $role == UserType::OFFICER)
        {
            $menu .= self::adminMenu($role);
        }

        if (!empty($role))
        {
            $menu .= self::userMenu();
        }

        $menu .= self::pagesMenu();
        return $menu;
    }

    private static function addMultilevelMenuItem($name, $route, $icon, $items) {
        self::$menu[$name] = array('multilevel' => true, 'route' => $route, 'icon' => $icon, 'items' => $items, 'active' => false);
    }

    private static function addMenuItem($name, $route, $icon, $globalUrl = false) {
        self::$menu[$name] = array('route' => $route, 'icon' => $icon, 'active' => false, 'multilevel' => false, 'globalUrl' => $globalUrl);
    }

    private static function setActiveItem() {
        foreach(self::$menu as &$item) {
            $uri = $_SERVER['REQUEST_URI'];
            if ($item['multilevel'] && strpos($uri, $item['route']) !== false) {
                $item['active'] = true;
                foreach($item['items'] as &$it) {
                    if (strpos($uri, $it['route']) !== false && !$it['globalUrl']) {
                        $it['active'] = true;
                    }
                }
            } else {
                if ($uri == $item['route'] && !$item['globalUrl']) {
                    $item['active'] = true;
                }
            }

        }
    }

    private static function generateHTML($baseUrl, $header) {

        self::setActiveItem();

        $content = '<li class="header">'.strtoupper($header).'</li>';
        $format = "<li class='%s'><a href='%s'><i class='fa fa-%s'></i> <span>%s</span></a></li>";

        foreach(self::$menu as $name => $data) {
            $statusAppendix = ($data['active']) ? 'active' : '';
            if ($data['multilevel']) {
                // multilevel
                $content .= '<li class="treeview '.$statusAppendix.'">';
                $content .= '<a href="#"><i class="fa fa-'.$data['icon'].'"></i> <span>' . $name . '</span><span class="pull-right-container"><i class="fa fa-angle-down pull-right"></i></span></a>';
                $content .= '<ul class="treeview-menu">';
                foreach($data['items'] as $item) {
                    $localStatus = ($item['active']) ? 'active' : '';
                    $localRoute = ($item['globalUrl']) ? $item['route'] : $baseUrl . $item['route'];
                    $icon = (!isset($item['icon'])) ? 'circle-o' : $item['icon'];
                    $content .= '<li class="'.$localStatus.'"><a href="' . $localRoute . '"><i class="fa fa-' . $icon . '"></i> ' . $item['name'] . '</a></li>';
                }
                $content .= '</ul></li>';
            } else {
                $writeRoute = ($data['globalUrl']) ? $data['route'] : $baseUrl . $data['route'];
                $content .= sprintf($format, $statusAppendix, $writeRoute, $data['icon'], $name);
            }


        }

        self::$menu = [];
        return $content;
    }
}