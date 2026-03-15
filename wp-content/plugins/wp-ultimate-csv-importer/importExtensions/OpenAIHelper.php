<?php
/******************************************************************************************
 * Copyright (C) Smackcoders. - All Rights Reserved under Smackcoders Proprietary License
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * You can contact Smackcoders at email address info@smackcoders.com.
 *******************************************************************************************/

 namespace Smackcoders\FCSV;

 if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

class OpenAIHelper {
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1/chat/completions';
    private $image_baseUrl = 'https://api.openai.com/v1/images/generations';
    public function generateContent($prompt, $maxWords = 0) {
        $settings = get_option('openAI_settings');
        
        // Handle legacy string settings or new JSON settings
        if (is_string($settings) && $json = json_decode($settings, true)) {
            if (isset($json['ai'])) {
                $settings = $json;
            } else {
                // It might be a JSON but not our settings structure, or just a key
                // If it's just a key, treat as legacy OpenAI
                $settings = ['ai' => 'chatgpt', 'apikey' => $settings, 'model' => 'gpt-3.5-turbo'];
            }
        } elseif (is_string($settings)) {
             $settings = ['ai' => 'chatgpt', 'apikey' => $settings, 'model' => 'gpt-3.5-turbo'];
        }

        $provider = isset($settings['ai']) ? $settings['ai'] : 'chatgpt';
        $this->apiKey = isset($settings['apikey']) ? $settings['apikey'] : '';
        $model = isset($settings['model']) ? $settings['model'] : '';

        if (empty($this->apiKey)) {
            return false;
        }

        switch ($provider) {
            case 'gemini':
                return $this->generateGeminiContent($prompt, $model, $maxWords);
            case 'claude':
                return $this->generateClaudeContent($prompt, $model, $maxWords);
            case 'chatgpt':
            default:
                return $this->generateOpenAIContent($prompt, $model, $maxWords);
        }
    }

    private function generateOpenAIContent($prompt, $model, $maxWords = 0) {
        $model = $model ?: 'gpt-3.5-turbo';
        if ($maxWords > 0) {
            $prompt .= " The response must be approximately $maxWords words long. Do not include the word count or any meta-information about the length in the output.";
        }
        $data = [
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'model' => $model,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $response = wp_remote_post($this->baseUrl, array(
            'body' => json_encode($data),
            'headers' => $headers,
            'timeout' => 60,
        ));

        return $this->processResponse($response, 'openai');
    }

    private function generateGeminiContent($prompt, $model, $maxWords = 0) {
        $model = $model ?: 'gemini-flash-latest';
        if ($maxWords > 0) {
            $prompt .= " The response must be approximately $maxWords words long. Do not include the word count or any meta-information about the length in the output.";
        }
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post($url, array(
            'body' => json_encode($data),
            'headers' => $headers,
            'timeout' => 60,
        ));

        return $this->processResponse($response, 'gemini');
    }

    private function generateClaudeContent($prompt, $model, $maxWords = 0) {
        $model = $model ?: 'claude-3-opus-20240229';
        if ($maxWords > 0) {
            $prompt .= " The response must be approximately $maxWords words long. Do not include the word count or any meta-information about the length in the output.";
        }
        $url = 'https://api.anthropic.com/v1/messages';

        $data = [
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        $response = wp_remote_post($url, array(
            'body' => json_encode($data),
            'headers' => $headers,
            'timeout' => 60,
        ));

        return $this->processResponse($response, 'claude');
    }

    private function processResponse($response, $provider) {
        if (is_wp_error($response)) {
            return "Error: " . $response->get_error_message();
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decodedResponse = json_decode($body, true);

        if ($httpCode != 200) {
            // Log error or return code
            return "Error ($httpCode): " . $body;
        }

        switch ($provider) {
            case 'openai':
                return isset($decodedResponse['choices'][0]['message']['content']) ? $decodedResponse['choices'][0]['message']['content'] : false;
            case 'gemini':
                return isset($decodedResponse['candidates'][0]['content']['parts'][0]['text']) ? $decodedResponse['candidates'][0]['content']['parts'][0]['text'] : false;
            case 'claude':
                return isset($decodedResponse['content'][0]['text']) ? $decodedResponse['content'][0]['text'] : false;
            default:
                return false;
        }
    }

    public function generateImage($prompt) {
        $get_key =get_option('openAI_settings');
        $this->apiKey = $get_key;
        $data = [
            'prompt' => $prompt,
            'model' => 'dall-e-3',
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        $response = wp_remote_post($this->image_baseUrl, array(
            'body' => json_encode($data),
            'headers' => $headers,
            'timeout' => 60,
        ));
        $httpCode = wp_remote_retrieve_response_code($response);
        $decodedResponse = json_decode(wp_remote_retrieve_body($response), true);

        if ($httpCode !== 200) {
            return $httpCode;
        }
        if (isset($decodedResponse['data'][0]['url'])) {
            return $decodedResponse['data'][0]['url'];
        } else {
            return false;
        }
    }
}