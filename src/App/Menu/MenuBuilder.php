<?php

namespace App\Menu;

use App\Provider\Model;
use App\Type\UserType;

class MenuBuilder
{

    private static $isGuest = false;

    public static function generate($userRole)
    {
        $menu = new Menu();

        if ($userRole == UserType::OFFICER || $userRole == UserType::ADMINISTRATOR)
        {
            $adminGroup = new MenuGroup('Administration');
            $adminGroup
                ->add(new MenuItem('Edit registration form', '/admin/signup/edit', 'user-plus'))
                ->add(new MenuItem('Users list', '/admin/users/list', 'list-ol'))
                ->add(new MenuItem('System settings', '/admin/settings', 'cog'))
                ->add(new MenuItem('Content management', null, 'file-text', [
                    new MenuItem('Create new page', '/admin/pages/create', 'plus'),
                    new MenuItem('Categories', '/admin/pages/categories', 'tag'),
                    new MenuItem('Pages list', '/admin/pages/list', 'list'),
                ]))
                ->add(new MenuItem('Tournaments', null, 'calendar', [
                    new MenuItem('Create new tournament', '/admin/tournament/create', 'plus'),
                    new MenuItem('Tournament list', '/admin/tournament/list', 'list'),
                ]))
                ->add(new MenuItem('User groups', '/admin/groups/list', 'users'))
                ->add(new MenuItem('Email templates', '/admin/email/list', 'envelope'))
                ->add(new MenuItem('Users transactions', '/admin/transactions/list', 'money'))
            ;
            $menu->add($adminGroup);
        }

        if (!empty($userRole))
        {
            $userGroup = new MenuGroup('Manage');
            $userGroup
                ->add(new MenuItem('Profile', '/user/profile', 'user'))
                ->add(new MenuItem('Balance', '/user/balance', 'money'))
                ->add(new MenuItem('Tournaments', '/tournament/list', 'calendar'))
            ;
            $menu->add($userGroup);
        }
        else
        {
            self::$isGuest = true;
        }

        $pagesGroup = new MenuGroup('Pages');
        $pagesGroup->add(new MenuItem('Home', '/', 'home'));

        $rootPages = Model::get('pages')->getAllByCategoryId(0);
        $pagesTree = Model::get('categories')->buildTree(Model::get('categories')->getAll(), 0, true);

        foreach($rootPages as $page)
        {
            if (($page['public'] || !self::$isGuest) && !in_array($page['slug'], ['home', 'terms']))
            {
                $pagesGroup->add(new MenuItem($page['name'], '/pages/' . $page['slug'], 'circle-o'));
            }
        }

        foreach($pagesTree as $page)
        {
            $pagesInCategory = [];
            foreach($page['pages'] as $p)
            {
                if ($p['public'] || !self::$isGuest)
                {
                    $pagesInCategory[] = new MenuItem($p['name'], '/pages/' . $p['slug'], 'circle-o');
                }
            }

            $pagesInCategory = self::_generateCategoryTree($page['childs'], $pagesInCategory);

            if (count($pagesInCategory) == 0) continue;
            $pagesGroup->add(new MenuItem($page['name'], '', 'circle-o', $pagesInCategory));
        }


        $menu->add($pagesGroup);

        return $menu->generate();
    }

    private static function _generateCategoryTree($childs, $pagesInCategory)
    {
        foreach ($childs as $row)
        {
            if (count($row['childs']) > 0)
            {
                $child = self::_generateCategoryTree($row['childs'], $pagesInCategory);
            }
            else
            {
                if (count($row['pages']) == 0) continue;
                $child = [];
            }

            foreach($row['pages'] as $page)
            {
                if ($page['public'] || !self::$isGuest)
                {
                    $child[] = new MenuItem($page['name'], '/pages/' . $page['slug'], 'circle-o');
                }
            }

            $data = new MenuItem($row['name'], '', 'circle-o', $child);
            $pagesInCategory[] = $data;
        }

        return $pagesInCategory;
    }
}