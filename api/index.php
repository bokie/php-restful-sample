<?php
/**
 * 入口文件负责请求转发
 */

//  print_r( $_SERVER ); [PATH_INFO]

require __DIR__.'/../lib/User.php';
require __DIR__.'/../lib/Article.php';
$pdo = require __DIR__.'/../lib/db.php';

class Api
{
    /**
     * @var User
     */
    private $_user;
    /**
     * @var Article
     */
    private $_article;

    /**
     * 请求方法
     * @var string
     */
    private $_requestMethod; 
    /**
     * 请求的资源名称
     * @var string
     */
    private $_resourceName;
    /**
     * 请求的资源id
     * @var string
     */
    private $_id;
    /**
     * 允许请求的资源列表
     * @var array
     */
    private $_allowResources = [ 'users', 'articles' ];
    /**
     * 允许的HTTP请求方法
     * @var array
     */
    private $_allowRequestMethods = [ 'GET', 'POST', 'PUT', 'DELETE', 'OPTIONS' ];

    /**
     * 常用响应状态码
     * @var array
     */
    private $_statusCodes = [
        200 => 'Ok',
        204 => 'No Content',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Server Internal Error',
    ];

    /**
     * Api Constructore
     */
    public function __construct ( User $_user, Article $_article ) {
        $this->_user    = $_user;
        $this->_article = $_article;
    }

    /**
     * 请求入口
     */
    public function run() {

        try {
            $this->_setupRequestMethod();
            $this->_setupResource();

            if ( $this->_resourceName == 'users' ) {
                $this->_json( $this->_handleUser() );
            } else if ( $this->_resourceName == 'articles' ) {
                $this->_json( $this->_handleArticle() );
            }

        } catch ( Exception $e ) { // 处理错误信息并返回
            $this->_json( [ 'error' => $e->getMessage(), ], $e->getCode() );
        }
    }

    /**
     * 初始化请求方法
     */
    private function _setupRequestMethod () {
        
        $this->_requestMethod = $_SERVER[ 'REQUEST_METHOD' ]; // 获取请求方法

        if ( ! in_array( $this->_requestMethod, $this->_allowRequestMethods ) ) {
            throw new Exception( '不被允许的请求方法', 405 );
        }

    }

    /**
     * 初始化请求资源
     */
    private function _setupResource () {

        $path = $_SERVER[ 'PATH_INFO' ];
        $params = explode( '/', $path ); // array '/user/1' => ['', 'user', '1']
        $this->_resourceName = $params[1];

        if ( ! in_array( $this->_resourceName, $this->_allowResources ) ) {
            throw new Exception( '不被允许的请求资源', 400 );
        }

        if ( ! empty( $params[2] ) ) { // 判断资源标识符是否为空
            $this->_id = $params[2];
        }

    }

    /**
     * 格式化json,生成返回数据
     * @param $array
     * @param $code
     */
    private function _json ( $array, $code = 0 ) {
        if ( $array === null && $code === 0 ) {
            $code = 204;            
        }
        if ( $array !== null && $code ===0 ) {
            $code = 200;            
        }

        header( "HTTP/1.1 " . $code . " " . $this->_statusCodes[ $code ] );
        header( 'Content-Type:application/json;charset=utf-8' );

        if ( $array !== null ) {
            echo json_encode( $array, JSON_UNESCAPED_UNICODE );
        }
        
        exit();
    }


    /**
     * 定义请求用户资源的处理逻辑
     * @return array
     */
    private function _handleUser () {

        if ( $this->_requestMethod != 'POST' ) {
            throw new Exception( '不被允许的请求方法', 405 );            
        }

        /** 读取请求体 */
        $body = $this->_getBodyParams();

        /** 数据校验 */
        if ( empty( $body[ 'username' ] ) ) {
            throw new Exception( '用户名不能为空', 400 );
        }
        if ( empty( $body[ 'password' ] ) ) {
            throw new Exception( '密码不能为空', 400 );
        }

        return $this->_user->register( $body[ 'username' ], $body[ 'password' ] );

    }
    
    /**
     * 定义请求文章资源的处理逻辑
     */
    private function _handleArticle () {
        /** POST / PUT / GET / DELETE  */
        switch ( $this->_requestMethod ) {
            case 'POST' :
                return $this->_articleCreate(); // 新建文章
            case 'PUT' :
                return $this->_articleEdit(); // 编辑文章
            case 'DELETE' :
                return $this->_articleDelete(); // 删除文章
            case 'GET' :
                if ( empty( $this->_id ) ) {
                    return $this->_articleGetList(); // 获取文章列表
                } else {
                    return $this->_articleGetItem(); // 获取单篇文章
                }
            default :
                throw new Exception( '不被允许的请求方法', 405 );
        }
    }

    /**
     * 创建文章
     * @return array
     */
    private function _articleCreate () {
        $body = $this->_getBodyParams();

        /** 数据检测 */
        if ( empty( $body[ 'title' ] ) ) {
            throw new Exception( '文章标题不能为空', 400 );
        }
        if ( empty( $body[ 'content' ] ) ) {
            throw new Exception( '文章内容不能为空', 400 );
        }

        // 用户验证
        $user = $this->_userLogin( $_SERVER[ 'PHP_AUTH_USER' ], $_SERVER[ 'PHP_AUTH_PW' ] );

        try {
            $article = $this->_article->create( $body[ 'title' ], $body[ 'content' ], $user[ 'userid' ] );
            return  $article;
        } catch ( Exception $e ) {
            if ( ! in_array( $e->getCode(),
                             [
                                 ErrorCode::ARTICLE_TITLE_REQUIRED,
                                 ErrorCode::ARTICLE_CONTENT_REQUIRED
                             ] )
                ) {
                    throw new Exception( $e->getMessage(), 400 ); // 重置状态码
            } else {
                throw new Exception( $e->getMessage(), 500 );
            }

        }

    }

    /**
     * 编辑文章
     */
    private function _articleEdit () {
        $user = $this->_userLogin( $_SERVER[ 'PHP_AUTH_USER' ], $_SERVER[ 'PHP_AUTH_PW' ] );
        try {
            $article = $this->_article->getArticle( $this->_id );
            
            if ( $article[ 'userid' ] != $user[ 'userid' ] ) {
                throw new Exception( '没有编辑权限', 403 );
            }
            
            /** 参数处理 */
            $body = $this->_getBodyParams();
            $title   = empty( $body[ 'title' ] ) ? $article[ 'title' ] : $body[ 'title' ];
            $content = empty( $body[ 'content' ] ) ? $article[ 'content' ] : $body[ 'content' ]; 
            if ( $title == $article[ 'title' ] && $content == $article[ 'content' ] ) {
                return $article;
            }

            return $this->_article->edit( $this->_id, $title, $content, $user[ 'userid' ] );

        } catch ( Exception $e ) {
            if ( $e->getCode() < 100 ) {
                if ( $e->getCode() == ErrorCode::ARTICLE_NOT_FOUND ) {
                    throw new Exception( $e->getMessage(), 404 );
                } else {
                    throw new Exception( $e->getMessage(), 400 );
                } 
            } else {
                throw $e;
            }
            
        }

    }

    private function _articleDelete () {
        $user = $this->_userLogin( $_SERVER[ 'PHP_AUTH_USER' ], $_SERVER[ 'PHP_AUTH_PW' ] );
        try {
            $article = $this->_article->getArticle( $this->_id );
            
            if ( $article[ 'userid' ] != $user[ 'userid' ] ) {
                throw new Exception( '没有编辑权限', 403 );
            }
            
            $this->_article->delete( $this->_id, $user[ 'userid' ] );
            
            return null;
    
        } catch ( Exception $e ) {
            if ( $e->getCode() < 100 ) {
                if ( $e->getCode() == ErrorCode::ARTICLE_NOT_FOUND ) {
                    throw new Exception( $e->getMessage(), 404 );
                } else {
                    throw new Exception( $e->getMessage(), 400 );
                } 
            } else {
                throw $e;
            }
        }
    }

    /**
     * 获取文章列表
     */
    private function _articleGetList () {
        $user = $this->_userLogin( $_SERVER[ 'PHP_AUTH_USER' ], $_SERVER[ 'PHP_AUTH_PW' ] );
        $page = isset( $_GET[ 'page' ] ) ? $_GET[ 'page' ] : 1;
        $size = isset( $_GET[ 'size' ] ) ? $_GET[ 'size' ] : 10;
        if ( $size > 100 ) {
            throw new Exception( '分页大小最大值为100', 400 );
        }
        return $this->_article->getList( $user[ 'userid' ], $page, $size );
    }

    private function _articleGetItem () {
        try {
            return $this->_article->getArticle( $this->_id );
        } catch ( Exception $e ) {
            if ( $e->getCode() == ErrorCode::ARTICLE_NOT_FOUND ) { // 文章不存在
                throw new Exception( $e->getMessage(), 404 );
            } else {
                throw new Exception( $e->getMessage(), 500 );
            }
        }
    }

    /**
     * 用户登录 (HTTP认证功能)
     * 客户端请求头 Authorization:Basic [$PHP_AUTH_USER username : $PHP_AUTH_PW password base64]
     */
    private function _userLogin ( $PHP_AUTH_USER, $PHP_AUTH_PW ) {
        try {
            return $this->_user->login( $PHP_AUTH_USER, $PHP_AUTH_PW );
        } catch ( Exception $e ) { // 异常捕获
            if ( in_array( $e->getCode(),
                          [
                           ErrorCode::USERNAME_EMPTY,
                           ErrorCode::PASSWORD_EMPTY,
                           ErrorCode::LOGIN_FAIL
                          ] ) 
                ) {
                throw new Exception( $e->getMessage(), 401 );
            }
            throw new Exception( $e->getMessage(), 500 );
        }
    }

    /**
     * 获取请求体数据
     * @return array
     */
    private function _getBodyParams () {

        /**
         * php:// — 访问各个输入/输出流（I/O streams）
         */
        $raw = file_get_contents( 'php://input' ); // 获取原始POST数据

        if ( empty( $raw ) ) {
            throw new Exception( '请求参数错误', 400 );
        }

        // json内容格式：必须使用双引号, 末行不能有逗号结尾

        return json_decode( $raw, true );
    }
}

$user    = new User( $pdo );
$article = new Article( $pdo );

$api = new Api( $user, $article );
$api->run();