<?php

require_once __DIR__.'/ErrorCode.php';

/**
 * 用户类
 */
class User
{
    /**
     * 数据库连接句柄
     * @var
     */
    private $_db;

    /**
     * 构造方法，
     * User construct
     * @param PDO $db PDO连接句柄
     */
    public function __construct ( $db ) {
        $this->_db = $db;
    }

    /**
     * 用户登录
     * @param string $username 用户名
     * @param string $password 用户密码
     */
    public function login ( $username, $password ) {
        
        // 空用户名检测
        if ( empty( $username ) ) {
            throw new Exception( '用户名不能为空', ErrorCode::USERNAME_EMPTY );
        }

        // 空密码检测
        if ( empty( $password ) ) {
            throw new Exception( '密码不能为空', ErrorCode::PASSWORD_EMPTY );
        }
        
        /** 数据库查询 */
        $sql = 'SELECT * FROM `user` WHERE `username` = :username AND `password` = :password';
        $password = $this->_md5( $password );
        $stmt = $this->_db->prepare( $sql );
        $stmt->bindParam( ':username', $username );
        $stmt->bindParam( ':password', $password );
        if ( ! $stmt->execute() ) {
            throw new Exception( '服务器内部错误', ErrorCode::SERVER_INTERNAL_ERROR );
        }
        $user = $stmt->fetch( PDO::FETCH_ASSOC );
        if ( empty( $user ) ) { // 查询结果判断
            throw new Exception( '用户名或密码错误', ErrorCode::LOGIN_FAIL );
        }

        unset( $user[ 'password' ] );
        return $user;

    }

    /**
     * 用户注册
     * @param $username 用户名
     * @param $password 用户密码
     */
    public function register ( $username, $password ) {
        
        // 空用户名检测
        if ( empty( $username ) ) {
            throw new Exception( '用户名不能为空', ErrorCode::USERNAME_EMPTY );
        }

        /** 检测用户名是否存在 */
        if ( $this->_isUsernameExists( $username ) ) {
            throw new Exception( '用户名已存在', ErrorCode::USERNAME_EXISITS );
        }

        // 空密码检测
        if ( empty( $password ) ) {
            throw new Exception( '密码不能为空', ErrorCode::PASSWORD_EMPTY );
        }

        /** 用户信息数据库写入 */
        $sql = 'INSERT INTO `user` ( `username`, `password`, `createdtime` ) VALUES ( :username, :password, :createdtime )';
        
        $createdtime = time();
        $password = $this->_md5( $password );

        $stmt = $this->_db->prepare( $sql );
        $stmt->bindParam( ':username', $username );
        $stmt->bindParam( ':password', $password );
        $stmt->bindParam( ':createdtime', $createdtime );
        if ( ! $stmt->execute() ) { // sql操作执行失败时抛出异常
            throw new Exception( '注册失败', ErrorCode::REGISTER_FAIL );
        }

        // 注册成功
        return [
            'userId'      => $this->_db->lastInsertId(),
            'username'    => $username,
            'createdtime' => $createdtime,
        ];


    }

    /**
     * 判断用户名是否存在
     * @param string $username 用户名
     * @return bool
     */
    private function _isUsernameExists ( $username ) {

        //  数据库查询
        $sql = 'SELECT * FROM `user` WHERE `username` = :username'; // sql 预处理语句
        $stmt = $this->_db->prepare( $sql );
        $stmt->bindParam( ':username', $username ); // 绑定变量
        $stmt->execute(); // 执行查询

        //  返回关联数组(FETCH_ASSOC) (默认返回索引数组和关联数组)
        $result = $stmt->fetch( PDO::FETCH_ASSOC );

        return !empty( $result );
    }

    /**
     * 字符串MD5加密
     * @param $string 待加密字符串 
     * @param $key 特征符
     */
    private function _md5 ( $string, $key = 'bokie' ) {
        return md5( $string . $key );
    }
}