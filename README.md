
# ISUCON 14 Practice

ISUCON 14 当日マニュアル          : https://gist.github.com/wtks/0a3268de13856ed6e18c6560023ec436
ISURIDE アプリケーションマニュアル : https://gist.github.com/wtks/8eadf471daf7cb59942de02273ce7884

## 開始直後に実施すること

### VS Code Remote SSH

1. 端末からコンテストサーバーに接続出来るように config に追加
2. アプリケーション開発用のサーバーについては GitHub へ接続可能な鍵を配置

### 言語実装の切り替え

1. 必要に応じて参考実装の言語を切り替え
2. 今回は PHP での練習をしているので当日マニュアルに従い下記のコマンドを実行
    - sudo systemctl disable --now isuride-go.service
    - sudo systemctl enable --now isuride-php.service
    - sudo ln -s /etc/nginx/sites-available/isuride-php.conf /etc/nginx/sites-enabled/
    - sudo systemctl restart nginx.service

### alp の導入

https://qiita.com/tsuzuki_takaaki/items/8d18ddb7698f0644c89e

1. 最新のリリースを確認 https://github.com/tkuchiki/alp/releases
2. wget https://github.com/tkuchiki/alp/releases/download/{バージョン}/alp_linux_amd64.tar.gz
3. tar xzf alp_linux_amd64.tar.gz
4. sudo install alp /usr/local/bin/alp
5. alp -v
    - バージョンが出れば OK

### Nginx ログの設定

1. nginx.config に alp 用のログ出力設定を追加
    ```
    log_format ltsv "time:$time_local\t"
                    "host:$remote_addr\t"
                    "forwardedfor:$http_x_forwarded_for\t"
                    "req:$request\t"
                    "status:$status\t"
                    "method:$request_method\t"
                    "uri:$request_uri\t"
                    "size:$body_bytes_sent\t"
                    "referer:$http_referer\t"
                    "ua:$http_user_agent\t"
                    "reqtime:$request_time\t"
                    "cache:$upstream_http_x_cache\t"
                    "runtime:$upstream_http_x_runtime\t"
                    "apptime:$upstream_response_time\t"
                    "vhost:$host";

    access_log /var/log/nginx/access.log ltsv;
    error_log /var/log/nginx/error.log;
    ```
2. alp 結果出力用のディレクトリを作成
    - mkdir alp

### デプロイコマンドの調整

1. deploy.sh をコンテストの状況に合わせて編集
2. sudo deploy.sh


## 継続的に実施すること

### ベンチマークを実行

### ベンチマーク実行後は alp による解析を実施

sudo alp ltsv --config alp.yml --file /var/log/nginx/access.log > alp/$(date +"%Y%m%d%H%M%S").txt
