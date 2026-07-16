# IP-Hammer
A little PHP page that lets you manage nginx-style IP blocks.

<details>
<summary>Screenshot</summary>

![The page with a list of banned IPs](/screenshots/image.png)

</details>


## How to setup
(this guide assumes you already have the IP-blocking setup on nginx)


### 1. Edit `index.php`
It is recommended to set yourself a password.
If you open up `index.php`, you can see some variables that can be configured.

```php
$password_hash = "";
$country_ban_path = "";
$manual_ban_path = "";
$auto_ban_path = "";
```

`$password_hash` should be a SHA256-hash of your password, with the salt of "ipbanner" attached to it. For example, if I wanted to use "test" as the password, I would use the SHA256 hash of **testipbanner**, (aka `7001463469ff2c7efb0850bf9645024c72a02d7adc1d69df6e98dcef20f9e301`)`

`$country_ban_path` should be a path to a .conf file that will contain all of the IP ranges banned due to them being in a certain country. I decided to use `/etc/nginx/blocked_ips/geo.conf`

`$manual_ban_path` should be a path to a .conf file that will contain all of the IP ranges banned manually through the web interface. I decided to use `/etc/nginx/blocked_ips/manual.conf`

`$auto_ban_path` should be a path to a .conf file that contains IP ranges that you have banned automatically, for example with a honeypot.. I used `/etc/nginx/blocked_ips/automatic.conf` for this one

### 2. Download `IP2LOCATION-LITE-DB1.CIDR` dataset
After you've registered an account on ip2location.net, download the dataset named `IP2LOCATION-LITE-DB1.CIDR.ZIP` on the [download page](https://www.ip2location.com/file-download)

### 3. Extract the dataset & place the CSV file in the same directory as `index.php`

### 4. Run PHP!
If you specified a password, you will be redirected to login. You may also see a header saying "Caching IPs to separate files..", this is a one time operation, but may take some time to finish.

### 5. Add attribution on your website
ip2location requires you to give them attribution if you don't pay for their dataset. 

### 6? Using your own dataset
If you want to use a different dataset, it has to be a CSV file (using commas, of course), with rows looking something like this:

```csv
"154.198.62.0/24","ZA","South Africa"
"154.198.63.0/24","FI","Finland"
"154.198.64.0/18","PK","Pakistan"
```