<?php
/**
 * 错误代码配置文件
 */

class ErrorCode
{
    const USERNAME_EXISITS = 1; // 用户名已存在
    const PASSWORD_EMPTY = 2; // 用户密码不能为空
    const USERNAME_EMPTY = 3; // 用户名不能为空
    const REGISTER_FAIL = 4; // 注册失败
    const LOGIN_FAIL = 5; // 登录失败
    const ARTICLE_TITLE_REQUIRED = 6; // 文章标题不能为空
    const ARTICLE_CONTENT_REQUIRED = 7; // 文章内容不能为空
    const ARTICLE_CREATE_FAIL = 8; // 文章发表失败
    const ARTICLE_ID_REQUIRED = 9; // 文章ID不能为空
    const ARTICLE_NOT_FOUND = 10; // 文章不存在
    const ARTICLE_PERMISSION_DENIED = 11; // 没有文章编辑权限
    const ARTICLE_UPDATE_FAIL = 12; // 文章编辑失败
    const ARTICLE_DELETE_FAIL = 13; // 文章删除失败
    const PAGE_SIZE_EXPIRED = 14; // 分页大小太大
    const SERVER_INTERNAL_ERROR = 15; // 服务器内部错误
}