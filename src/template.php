<?php

// 下面注释为注意事项 ！！！

// install.php脚本注意事项！！！
// 1. search、content值两侧均用英文双引号
// 2. 换行用'\n'代替
// 3. '$'用'\$'代替
// 4. '\'用'\\'代替
// 5. 为了执行结果的美观，建议细心跳转换行和空格

return [
    [
        'file' => '/application/common/controller/foo.php',
        'change' => [
            [
                'type' => 'after',
                'search' => "use app\admin\library\Auth;",
                'content' => "\nuse fuyelk\\redis\\Redis;"
            ],
            [
                'type' => 'before',
                'search' => "protected \$importHeadType = 'comment';",
                'content' => "\n\n    /**\n     * @var Redis|null\n     */\n    protected \$redis = null;"
            ],
            [
                'type' => 'replace',
                'search' => "foobar",
                'content' => "foo"
            ]
        ]
    ],
    [
        'file' => '/application/common/controller/bar.php',
        'change' => [
            [
                'type' => 'delete',
            ]
        ]
    ],
];