<?php
/**
 * Article 文章类
 * 文章操作相关逻辑处理
 */

 require_once __DIR__.'/ErrorCode.php';

 class Article
 {
     /**
      * 数据库句柄
      * @var 
      */
     private $_db;

     /**
      * Article constructor
      * @param PDO $_db 数据库连接句柄
      */
     public function __construct( $_db ) {
         $this->_db = $_db;
     }

     /**
      * 创建新的文章
      * @param  $title 文章标题
      * @param  $content 文章内容
      * @param  $userId 用户Id
      */
     public function create ( $title, $content, $userId ) {
         /** 空值判断 */
         if ( empty( $title ) ) {
             throw new Exception( '文章标题不能为空', ErrorCode::ARTICLE_TITLE_REQUIRED );
         }
         if ( empty( $content ) ) {
             throw new Exception( '文章内容不能为空', ErrorCode::ARTICLE_CONTENT_REQUIRED );
         }

         /** 数据库写入 */
         $sql = 'INSERT INTO `article` ( `userid`, `title`, `content`, `createdtime` )
                 VALUES ( :userId, :title, :content, :createdtime )';
         $createdtime = time();
         $stmt = $this->_db->prepare( $sql );
         $stmt->bindParam( ':userId', $userId );
         $stmt->bindParam( ':title', $title );
         $stmt->bindParam( ':content', $content );
         $stmt->bindParam( ':createdtime', $createdtime );
         if ( ! $stmt->execute() ) { // 执行失败时抛出异常
             throw new Exception( '文章发表失败', ErrorCode::ARTICLE_CREATE_FAIL );
         }

         return [
             'articleId'   => $this->_db->lastInsertId(),
             'title'       => $title,
             'content'     => $content,
             'createdtime' => $createdtime,
         ];
     }

     /**
      * 获取文章内容
      * @param $articleId 文章Id
      */
     public function getArticle ( $articleId ) {
         /** 空值判断 */
         if ( empty( $articleId ) ) {
             throw new Exception( '文章 ID 不能为空', ErrorCode::ARTICLE_ID_REQUIRED );
         }

         /** 数据库查询 */
         $sql = 'SELECT * FROM `article` WHERE `articleid` = :articleid';
         $stmt = $this->_db->prepare( $sql );
         $stmt->bindParam( ':articleid', $articleId );
         $stmt->execute();
         $article = $stmt->fetch( PDO::FETCH_ASSOC );

         if ( empty( $article ) ) { // 判断文章是否存在
            throw new Exception( '当前文章内容不存在', ErrorCode::ARTICLE_NOT_FOUND );
         }

         return $article;

     }

     /**
      * 编辑文章
      * @param  $articleId 文章Id
      * @param  $title 文章标题
      * @param  $content 文章内容
      * @param  $userId 用户Id
      */
     public function edit ( $articleId, $title, $content, $userId ) {
         
         $article = $this->getArticle( $articleId ); // 获取文章信息

         if ( $article[ 'userid' ] !== $userId ) {
             throw new Exception( '没有文章编辑权限', ErrorCode::ARTICLE_PERMISSION_DENIED );
         }

         $title = empty( $title ) ? $article[ 'title' ] : $title;
         $content = empty( $content ) ? $article[ 'content' ] : $content;
         if ( $title === $article[ 'title' ] && $content === $article[ 'content' ] ) {
             return $article;
         }

         /** 数据库写入 */
         $sql = 'UPDATE `article` SET `title` = :title, `content` = :content WHERE `articleid` = :articleid';
         $stmt = $this->_db->prepare( $sql );
         $stmt->bindParam( ':title', $title );
         $stmt->bindParam( ':content', $content );
         $stmt->bindParam( ':articleid', $articleId );
         if ( ! $stmt->execute() ) { // 执行失败时抛出异常
            throw new Exception( '文章编辑失败', ErrorCode::ARTICLE_UPDATE_FAIL );
         }

         return [
             'articleid'   => $articleId,
             'title'       => $title,
             'content'     => $content,
             'createdtime' => $article[ 'createdtime' ],
         ];
         
     }

     /**
      * 删除文章
      * @param  $articleId 文章Id 
      * @param  $userId 用户Id
      */
     public function delete ( $articleId, $userId ) {

         // 判断文章是否存在
         $article = $this->getArticle( $articleId );
         if ( $article[ 'userid' ] !== $userId ) {
             throw new Exception( '无权操作', ErrorCode::ARTICLE_PERMISSION_DENIED );
         }
          
         /** 数据库删除操作 */
         $sql = 'DELETE FROM `article` WHERE `articleid` = :aid AND `userid` = :uid';
         $stmt = $this->_db->prepare( $sql );
         $stmt->bindParam( ':aid', $articleId );
         $stmt->bindParam( ':uid', $userId );
         if ( ! $stmt->execute() ) { // 删除操作执行失败时抛出异常 ( === )
            throw new Exception( '文章删除失败', ErrorCode::ARTICLE_DELETE_FAIL );
         }

         return true;

     }

     /**
      * 获取文章列表
      * @param  $userId 用户Id
      * @param  $page 页数
      * @param  $size 文章数目
      */
     public function getList ( $userId, $page = 1, $size = 10 ) {

        if ( $size > 100 ) {
            throw new Exception( '分页大小最大为100', ErrorCode::PAGE_SIZE_EXPIRED );
        }

        /** 数据库查询 */
         $sql = 'SELECT * FROM `article` WHERE `userid` = :uid LIMIT :limit, :offset';
         $limit = ( $page - 1 ) * $size;
         $limit = $limit < 0 ? 0 : $limit;
         $stmt = $this->_db->prepare( $sql );
         $stmt->bindParam( ':uid', $userId );
         $stmt->bindParam( ':limit', $limit );
         $stmt->bindParam( ':offset', $size );
         $stmt->execute();
         $data = $stmt->fetchAll( PDO::FETCH_ASSOC );

         return $data;
     }

 }