<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\FlashMessage;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\UserType;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PagesModel extends BaseModel
{
    public function create($name, $slug, $categoryId, $public, $content, $userId)
    {
        // check exists
        $exists = MySQL::get()->fetchColumn('SELECT id FROM pages WHERE `name` = :n', [
            'n' => $name
        ]);

        if (!$exists)
        {
            $sql = 'INSERT INTO pages (category_id, slug, `name`, `public`) VALUES (:cId, :s, :n, :p)';
            $pageId = MySQL::get()->exec($sql, [
                'cId' => $categoryId,
                's' => $slug,
                'n' => $name,
                'p' => $public
            ]);

            // create page content
            $sql = 'INSERT INTO pages_content (page_id, content, author_id) VALUES (:pId, :content, :aId)';
            MySQL::get()->exec($sql, [
                'pId' => $pageId,
                'content' => $content,
                'aId' => $userId
            ]);

            return true;
        }
        else
        {
            return StatusCode::PAGE_SLUG_EXISTS;
        }
    }

    public function getBySlug($slug)
    {
        $sql = 'SELECT p.name, p.public, pc.content, pcat.name as category_name
                FROM pages p
                INNER JOIN pages_content pc ON pc.page_id = p.id
                LEFT JOIN pages_category pcat ON pcat.id = p.category_id
                WHERE p.slug = :s
                ORDER BY pc.timestamp DESC
                LIMIT 1';
        $pageData = MySQL::get()->fetchOne($sql, ['s' => $slug]);
        return $pageData;
    }
}