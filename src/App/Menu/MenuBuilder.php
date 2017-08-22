<?php

namespace App\Menu;

use App\Type\UserType;

class MenuBuilder
{

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
            ;
            $menu->add($adminGroup);
        }

        if (!empty($userRole))
        {
            $userGroup = new MenuGroup('Manage');
            $userGroup
                ->add(new MenuItem('Profile', '/user/profile', 'user'))
                ->add(new MenuItem('Balance', '/user/balance', 'money'))
            ;
            $menu->add($userGroup);
        }

        $pagesGroup = new MenuGroup('Pages');
        $pagesGroup
            ->add(new MenuItem('Test page item', '/404', 'user'))
        ;
        $menu->add($pagesGroup);

        return $menu->generate();
    }
}