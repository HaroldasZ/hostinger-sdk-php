<?php

class HostingerApi
{
    protected $username = '';
    protected $password = '';
    protected $api_url = '';

    /**
     * $config['username'] string
     * $config['password'] string
     * $config['api_url']  string Must end with '/'
     *
     * @param array $config (See above)
     */
    public function __construct($config)
    {
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->api_url = $config['api_url'];
    }

    /**
     * @param string $name
     * @param string $email
     * @param string $subject
     * @param string $content
     * @return array
     * @throws HostingerApiException
     */
    public function publicTicketCreate($name, $email, $subject, $content){
        $params = array(
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'content' => $content,
            'ip' => $this->getIp(),
        );
        return $this->make_call('v1/ticket/create_public', 'POST', $params);
    }

    /**
     * @param string $email
     * @return array
     * @throws HostingerApiException
     */
    public function clientGetByEmail($email){
        $params = array(
            'email' => $email
        );
        return $this->make_call('v1/client/get-by-email', 'GET', $params);
    }

    /**
     * @param string $first_name
     * @param string $password
     * @param string $email
     * @param array $additionalParams
     * @return array
     * @throws HostingerApiException
     */
    public function clientCreate($first_name, $password, $email, $additionalParams = array())
    {
        $params = array(
            'email' => $email,
            'password' => $password,
            'first_name' => $first_name,
            'client_ip' => $this->getIp(),
        );

        $defaultAdditionalParams = array(
            'last_name' => '',
            'company' => '',
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'country' => '',
            'state' => '',
            'zip' => '',
            'phone' => '',
            'phone_cc' => '',
            'cpf' => '',
            'referral_id' => '',
            'reseller_client_campaign' => '',
            'reseller_client_campaign_source' => '',
            'r' => '',
        );

        $additionalParams = array_merge($defaultAdditionalParams, $additionalParams);
        $params = array_merge($additionalParams, $params);

        return $this->make_call('v1/client', 'POST', $params);
    }

    /**
     * @param string $from_email
     * @param string $from_name
     * @param string $to_email
     * @param string $to_name
     * @param string $subject
     * @param string $content_html
     * @param string $content_txt
     * @return array
     * @throws HostingerApiException
     */
    public function mailSend($from_email, $from_name, $to_email, $to_name, $subject, $content_html, $content_txt) {
        $params = array(
            'subject'       => $subject,
            'from_email'    => $from_email,
            'from_name'     => $from_name,
            'body_html'     => $content_html,
            'body_text'     => $content_txt,
            'to_email'      => $to_email,
            'to_name'       => $to_name,
        );
        return $this->make_call('v1/mail/send', 'POST', $params);
    }

    /**
     * @return string
     */
    private function getIp()
    {
        $address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        if (is_string($address)) {
            if (strpos($address, ',') !== false) {
                $address = end(explode(',', $address));
            }
        }
        if (is_null($address)) {
            $address = $_SERVER['REMOTE_ADDR'];
        }
        return $address;
    }

    /**
     * @param string $cmd
     * @param string $method
     * @param array $post_fields
     * @return array
     * @throws HostingerApiException
     */
    private function make_call($cmd, $method = 'GET', $post_fields = array())
    {
        $result = $this->get_url($this->api_url.$cmd, $method, $post_fields, $this->username, $this->password);
        $result = json_decode($result, 1);
        if (isset($result['error']['message']) && !empty($result['error']['message'])) {
            throw new \HostingerApiException($result['error']['message'], $result['error']['code']);
        }
        return $result['result'];
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $post_fields
     * @param string $user
     * @param string $password
     * @param int $timeout
     * @return array
     * @throws HostingerApiException
     */
    private function get_url($url, $method, $post_fields = array(), $user = null, $password = null, $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if ($user && $password) {
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$password");
        }

        switch (strtolower($method)) {
            case'delete' :
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            case 'post' :
                $fields = http_build_query($post_fields, null, '&');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
                break;
            case 'get' :
                break;
        }

        $data = curl_exec($ch);
        curl_close($ch);
        if ($data === false) {
            throw new \HostingerApiException("Service is temporary unavailable.");
        }
        return $data;
    }
}