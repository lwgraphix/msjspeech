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
        $exists = MySQL::get()->fetchColumn('SELECT id FROM pages WHERE `slug` = :n', [
            'n' => $slug
        ]);

        if (!$exists)
        {
            $sql = 'INSERT INTO pages (category_id, slug, `name`, `public`) VALUES (:cId, :s, :n, :p)';
            $pageId = MySQL::get()->exec($sql, [
                'cId' => $categoryId,
                's' => $slug,
                'n' => $name,
                'p' => $public
            ], true);

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

    public function update($id, $name, $slug, $categoryId, $public, $content, $reason, $userId, $page)
    {
        // check exists
        $exists = MySQL::get()->fetchColumn('SELECT id FROM pages WHERE `slug` = :n AND id != :i', [
            'n' => $slug,
            'i' => $id
        ]);

        if (!$exists)
        {
            if ($page['content'] != $content)
            {
                // differs - create history item
                $sql = 'INSERT INTO pages_content (page_id, content, author_id, reason) VALUES (:pId, :content, :aId, :r)';
                MySQL::get()->exec($sql, [
                    'pId' => $id,
                    'content' => $content,
                    'aId' => $userId,
                    'r' => $reason
                ]);
            }

            // update
            $sql = 'UPDATE pages SET `name` = :n, category_id = :ci, slug = :s, `public` = :p WHERE id = :i';
            MySQL::get()->exec($sql, [
                'n' => $name,
                'ci' => $categoryId,
                's' => $slug,
                'p' => $public,
                'i' => $id
            ]);
            return true;
        }
        else
        {
            return StatusCode::PAGE_SLUG_EXISTS;
        }
    }

    public function delete($pageId)
    {
        // delete it's content
        $sql = 'DELETE FROM pages_content WHERE page_id = :pId';
        MySQL::get()->exec($sql, ['pId' => $pageId]);

        $sql = 'DELETE FROM pages WHERE id = :pId';
        MySQL::get()->exec($sql, ['pId' => $pageId]);

        return true;
    }

    public function getAllByCategoryId($categoryId)
    {
        $sql = 'SELECT * FROM pages WHERE category_id = :cid';
        $data = MySQL::get()->fetchAll($sql, ['cid' => $categoryId]);
        return $data;
    }

    public function getHistory($pageId)
    {
        $sql = 'SELECT pc.*, u.first_name, u.last_name, u.email
                FROM pages_content pc
                INNER JOIN users u ON u.id = pc.author_id
                WHERE pc.page_id = :pId
                ORDER BY pc.timestamp DESC
                ';
        $data = MySQL::get()->fetchAll($sql, ['pId' => $pageId]);
        return $data;
    }

    public function getAll()
    {
        $sql = 'select p.id, p.slug, p.name, pcc.author_id, p.public, pc.name as category_name, pcc.timestamp, pcc.reason, u.first_name, u.last_name, u.email
                from pages_content pcc
                inner join (
                    select page_id, max(timestamp) as last_date
                    from pages_content
                    group by page_id
                ) tm on pcc.page_id = tm.page_id and pcc.timestamp = tm.last_date
                inner join pages p ON p.id = tm.page_id
                LEFT JOIN pages_category pc ON pc.id = p.category_id
                INNER JOIN users u ON u.id = pcc.author_id';
        $data = MySQL::get()->fetchAll($sql);
        return $data;
    }

    public function getBySlug($slug)
    {
        $sql = 'SELECT p.id, p.slug, p.name, p.public, pc.content, pcat.id as category_id, pcat.name as category_name
                FROM pages p
                INNER JOIN pages_content pc ON pc.page_id = p.id
                LEFT JOIN pages_category pcat ON pcat.id = p.category_id
                WHERE p.slug = :s
                ORDER BY pc.timestamp DESC
                LIMIT 1';
        $pageData = MySQL::get()->fetchOne($sql, ['s' => $slug]);
        return $pageData;
    }

    public function getById($id)
    {
        $sql = 'SELECT p.id, p.slug, p.name, p.public, pc.content, pcat.id as category_id, pcat.name as category_name
                FROM pages p
                INNER JOIN pages_content pc ON pc.page_id = p.id
                LEFT JOIN pages_category pcat ON pcat.id = p.category_id
                WHERE p.id = :id
                ORDER BY pc.timestamp DESC
                LIMIT 1';
        $pageData = MySQL::get()->fetchOne($sql, ['id' => $id]);
        return $pageData;
    }
}