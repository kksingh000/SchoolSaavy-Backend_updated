<?php

namespace App\Services;

use Google\Client as GoogleClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private $client;
    private $projectId;
    private $serviceAccountPath;

    public function __construct()
    {
        $this->client = new Client();
        $this->projectId = config('services.firebase.project_id');
        $this->serviceAccountPath = config('services.firebase.service_account_path');
        
        // Validate configuration
        if (empty($this->projectId)) {
            throw new \Exception('Firebase project ID is not configured. Please set FIREBASE_PROJECT_ID in your environment.');
        }
        
        if (empty($this->serviceAccountPath)) {
            throw new \Exception('Firebase service account path is not configured. Please set FIREBASE_SERVICE_ACCOUNT_PATH in your environment.');
        }
    }

    /**
     * Send push notification to single token
     */
    public function sendToToken(string $token, array $notification, array $data = []): array
    {
        try {
            $message = $this->buildMessage($token, $notification, $data);
            return $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Firebase send to token failed', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send push notification to multiple tokens
     */
    public function sendToTokens(array $tokens, array $notification, array $data = []): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($tokens as $token) {
            $result = $this->sendToToken($token, $notification, $data);
            $results[$token] = $result;

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        return [
            'success' => $failureCount === 0,
            'total' => count($tokens),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * Send push notification to topic
     */
    public function sendToTopic(string $topic, array $notification, array $data = []): array
    {
        try {
            $message = [
                'topic' => $topic,
                'notification' => $notification,
                'data' => $this->convertDataToStrings($data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'default',
                        'sound' => 'default'
                    ]
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10'
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1
                        ]
                    ]
                ]
            ];

            return $this->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Firebase send to topic failed', [
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Subscribe tokens to topic
     */
    public function subscribeToTopic(array $tokens, string $topic): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->post("https://iid.googleapis.com/iid/v1:batchAdd", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => '/topics/' . $topic,
                    'registration_tokens' => $tokens
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'results' => $result
            ];
        } catch (\Exception $e) {
            Log::error('Firebase subscribe to topic failed', [
                'topic' => $topic,
                'tokens' => $tokens,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Unsubscribe tokens from topic
     */
    public function unsubscribeFromTopic(array $tokens, string $topic): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->post("https://iid.googleapis.com/iid/v1:batchRemove", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => '/topics/' . $topic,
                    'registration_tokens' => $tokens
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'results' => $result
            ];
        } catch (\Exception $e) {
            Log::error('Firebase unsubscribe from topic failed', [
                'topic' => $topic,
                'tokens' => $tokens,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate Firebase token
     */
    public function validateToken(string $token): bool
    {
        try {
            $result = $this->sendToToken($token, [
                'title' => 'Test',
                'body' => 'Token validation'
            ], ['validate' => 'true']);

            return $result['success'];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Build FCM message structure
     */
    private function buildMessage(string $token, array $notification, array $data = []): array
    {
        // Ensure all data values are strings and clean
        $cleanData = $this->convertDataToStrings($data);
        
        return [
            'token' => $token,
            'notification' => [
                'title' => (string) ($notification['title'] ?? ''),
                'body' => (string) ($notification['body'] ?? ''),
            ],
            'data' => $cleanData,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'default',
                    'sound' => 'default',
                ]
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10'
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                        'category' => 'GENERAL'
                    ]
                ]
            ]
        ];
    }

    /**
     * Send message to Firebase
     */
    private function sendMessage(array $message): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => $message
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'message_id' => $result['name'] ?? null,
                'response' => $result
            ];
        } catch (RequestException $e) {
            $errorMessage = 'HTTP Error: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error']['message'] ?? $errorMessage;
            }

            Log::error('Firebase send message failed', [
                'error' => $e,
                'message' => $message
            ]);

            return [
                'success' => false,
                'error' => $errorMessage
            ];
        }
    }

    /**
     * Get OAuth2 access token
     */
    private function getAccessToken(): string
    {
        if (!file_exists($this->serviceAccountPath)) {
            throw new \Exception('Firebase service account file not found: ' . $this->serviceAccountPath);
        }

        $client = new GoogleClient();
        $client->setAuthConfig($this->serviceAccountPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $accessToken = $client->fetchAccessTokenWithAssertion();

        if (isset($accessToken['error'])) {
            throw new \Exception('Failed to get access token: ' . $accessToken['error']);
        }

        return $accessToken['access_token'];
    }

    /**
     * Convert data array values to strings (FCM requirement)
     */
    private function convertDataToStrings(array $data): array
    {
        $stringData = [];
        foreach ($data as $key => $value) {
            // Skip null values and empty arrays
            if ($value === null || (is_array($value) && empty($value))) {
                continue;
            }
            
            // Convert to string, handling arrays and objects properly
            if (is_array($value) || is_object($value)) {
                $stringData[$key] = json_encode($value);
            } else {
                $stringData[$key] = (string) $value;
            }
            
            // Validate key names (Firebase doesn't allow certain characters)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                Log::warning("Invalid Firebase data key: {$key}. Skipping.");
                unset($stringData[$key]);
            }
        }
        return $stringData;
    }

    /**
     * Create topic name for school
     */
    public static function getSchoolTopic(int $schoolId, string $suffix = ''): string
    {
        $topic = "school_{$schoolId}";
        if ($suffix) {
            $topic .= "_{$suffix}";
        }
        return $topic;
    }

    /**
     * Create topic name for class
     */
    public static function getClassTopic(int $schoolId, int $classId, string $suffix = ''): string
    {
        $topic = "school_{$schoolId}_class_{$classId}";
        if ($suffix) {
            $topic .= "_{$suffix}";
        }
        return $topic;
    }

    /**
     * Create topic name for user role
     */
    public static function getUserRoleTopic(int $schoolId, string $role): string
    {
        return "school_{$schoolId}_{$role}";
    }
}
