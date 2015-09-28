# SqlXS
SqlXS is a small yet complex library that takes your database models to a higher
level.

## Features
* Automatic getters and setters for fields in database objects. + You can restrict read- and writability.
* Referencing selects: `Post::select()->where('author', QueryBuilder::REFS, $user)->find()`
* No more double objects: all created objects are buffered, so when you modify one, all relations to the object are updated as well. (For example: a blog post and it's author, and a reply by that same author. When you change the name of the author of the reply (`$reply->getAuthor()->setUsername('Admin')`), the name of the author of the blog post (`$post->getAuthor()->getUsername()`) is updated as well)
* It contains a query builder that allows you to generate complex queries without
much understanding of MySQL. That's why it's called a generator.
* **Much more awesomeness**

## How to use?

    use Xesau\SqlXS;

    class MyObject
    {
        use SqlXS\XS;
        public static function sqlXS()
        {
            return new SqlXS\XsInfo('table name', 'unique field', ['readable', 'fields'], ['writable', 'fields'], ['field' => '\\Type']);
        }
     }

    // Get Object with the unique field's value = 1
    $obj = MyObject::byID(1);

    // Echo out the weight of the object
    echo $obj->getWeight() . ' pounds.';

    $obj->setWeight(40);
