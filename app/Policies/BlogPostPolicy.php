<?php

namespace App\Policies;

/**
 * Owned via `author_id`: the creator may always edit and delete their own record;
 * acting on someone else's requires admin.blog-posts.edit-any / .delete-any.
 */
class BlogPostPolicy extends BasePolicy
{
    protected string $resource = 'blog-posts';

    protected ?string $ownerColumn = 'author_id';
}
