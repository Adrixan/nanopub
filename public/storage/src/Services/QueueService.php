<?php

declare(strict_types=1);

namespace NanoPub\Services;

use NanoPub\Models\Queue\ActivityQueue;
use NanoPub\Models\Queue\DeliveryQueue;

/**
 * Queue management service.
 */
final class QueueService
{
    private ActivityPubService $activityPubService;
    
    /**
     * Maximum retry attempts.
     */
    private const MAX_ATTEMPTS = 5;
    
    public function __construct()
    {
        $this->activityPubService = new ActivityPubService();
    }
    
    /**
     * Process next activity from queue.
     * 
     * @return bool True if an activity was processed
     */
    public function processNextActivity(): bool
    {
        $item = ActivityQueue::getNext();
        
        if ($item === null) {
            return false;
        }
        
        ActivityQueue::markProcessing($item['id']);
        
        try {
            $activityData = json_decode($item['activity_data'], true);
            
            if ($activityData === null) {
                ActivityQueue::markFailed($item['id'], 'Invalid activity data');
                return false;
            }
            
            $this->activityPubService->handleActivity(
                $activityData,
                $item['actor']
            );
            
            ActivityQueue::markCompleted($item['id']);
            return true;
        } catch (\Throwable $e) {
            $attempts = ActivityQueue::incrementAttempts($item['id']);
            
            if ($attempts >= self::MAX_ATTEMPTS) {
                ActivityQueue::markFailed($item['id'], $e->getMessage());
            } else {
                // Reset to pending for retry
                ActivityQueue::retry($item['id']);
            }
            
            return false;
        }
    }
    
    /**
     * Process next delivery from queue.
     * 
     * @return bool True if a delivery was processed
     */
    public function processNextDelivery(): bool
    {
        $item = DeliveryQueue::getNext();
        
        if ($item === null) {
            return false;
        }
        
        DeliveryQueue::markProcessing($item['id']);
        
        try {
            $success = $this->activityPubService->deliverActivity($item);
            
            if ($success) {
                DeliveryQueue::markCompleted($item['id']);
            } else {
                $attempts = DeliveryQueue::incrementAttempts($item['id']);
                
                if ($attempts >= self::MAX_ATTEMPTS) {
                    DeliveryQueue::markFailed($item['id'], 'Delivery failed after max attempts');
                } else {
                    // Reset to pending for retry
                    DeliveryQueue::retry($item['id']);
                }
            }
            
            return $success;
        } catch (\Throwable $e) {
            $attempts = DeliveryQueue::incrementAttempts($item['id']);
            
            if ($attempts >= self::MAX_ATTEMPTS) {
                DeliveryQueue::markFailed($item['id'], $e->getMessage());
            } else {
                // Reset to pending for retry
                DeliveryQueue::retry($item['id']);
            }
            
            return false;
        }
    }
    
    /**
     * Process batch of activities.
     * 
     * @param int $count Maximum number to process
     * @return int Number of activities processed successfully
     */
    public function processActivityBatch(int $count = 10): int
    {
        $processed = 0;
        
        for ($i = 0; $i < $count; $i++) {
            if ($this->processNextActivity()) {
                $processed++;
            } else {
                break;
            }
        }
        
        return $processed;
    }
    
    /**
     * Process batch of deliveries.
     * 
     * @param int $count Maximum number to process
     * @return int Number of deliveries processed successfully
     */
    public function processDeliveryBatch(int $count = 10): int
    {
        $processed = 0;
        
        for ($i = 0; $i < $count; $i++) {
            if ($this->processNextDelivery()) {
                $processed++;
            } else {
                break;
            }
        }
        
        return $processed;
    }
    
    /**
     * Process both activity and delivery queues.
     * 
     * @param int $activityCount Maximum activities to process
     * @param int $deliveryCount Maximum deliveries to process
     * @return array{activities: int, deliveries: int} Processed counts
     */
    public function processQueues(int $activityCount = 10, int $deliveryCount = 10): array
    {
        return [
            'activities' => $this->processActivityBatch($activityCount),
            'deliveries' => $this->processDeliveryBatch($deliveryCount),
        ];
    }
    
    /**
     * Get queue statistics.
     * 
     * @return array{activity: array, delivery: array}
     */
    public function getStats(): array
    {
        return [
            'activity' => ActivityQueue::getStats(),
            'delivery' => DeliveryQueue::getStats(),
        ];
    }
    
    /**
     * Get pending counts.
     * 
     * @return array{activity: int, delivery: int}
     */
    public function getPendingCounts(): array
    {
        return [
            'activity' => ActivityQueue::getPendingCount(),
            'delivery' => DeliveryQueue::getPendingCount(),
        ];
    }
    
    /**
     * Cleanup old queue items.
     * 
     * @param int $daysOld Delete items older than this many days
     * @return array{activity: int, delivery: int} Deleted counts
     */
    public function cleanup(int $daysOld = 7): array
    {
        return [
            'activity' => ActivityQueue::cleanup($daysOld),
            'delivery' => DeliveryQueue::cleanup($daysOld),
        ];
    }
    
    /**
     * Retry all failed activities.
     * 
     * @return int Number of activities retried
     */
    public function retryAllFailedActivities(): int
    {
        $count = 0;
        
        foreach (ActivityQueue::getFailed(100) as $item) {
            if (ActivityQueue::retry($item['id'])) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Retry all failed deliveries.
     * 
     * @return int Number of deliveries retried
     */
    public function retryAllFailedDeliveries(): int
    {
        $count = 0;
        
        foreach (DeliveryQueue::getFailed(100) as $item) {
            if (DeliveryQueue::retry($item['id'])) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Enqueue an activity for processing.
     * 
     * @param string $type Activity type
     * @param array $data Activity data
     * @param string $actor Actor URL
     * @param int $priority Priority (higher = more important)
     * @return int Queue item ID
     */
    public function enqueueActivity(string $type, array $data, string $actor, int $priority = 0): int
    {
        return ActivityQueue::enqueue($type, $data, $actor, $priority);
    }
    
    /**
     * Enqueue a delivery for processing.
     * 
     * @param string $activityId Activity ID
     * @param string $type Activity type
     * @param array $data Activity data
     * @param string $inbox Target inbox URL
     * @param int $signingAccountId Account ID for signing
     * @param int $priority Priority
     * @return int Queue item ID
     */
    public function enqueueDelivery(
        string $activityId,
        string $type,
        array $data,
        string $inbox,
        int $signingAccountId,
        int $priority = 0
    ): int {
        return DeliveryQueue::enqueue(
            $activityId,
            $type,
            $data,
            $inbox,
            $signingAccountId,
            $priority
        );
    }
    
    /**
     * Enqueue delivery to multiple inboxes (fan-out).
     * 
     * @param string $activityId Activity ID
     * @param string $type Activity type
     * @param array $data Activity data
     * @param array $inboxes Target inbox URLs
     * @param int $signingAccountId Account ID for signing
     * @param int $priority Priority
     * @return int Number of deliveries enqueued
     */
    public function enqueueDeliveryFanOut(
        string $activityId,
        string $type,
        array $data,
        array $inboxes,
        int $signingAccountId,
        int $priority = 0
    ): int {
        return DeliveryQueue::enqueueFanOut(
            $activityId,
            $type,
            $data,
            $inboxes,
            $signingAccountId,
            $priority
        );
    }
    
    /**
     * Check if queues are healthy.
     * 
     * @return array{healthy: bool, issues: array<string>}
     */
    public function healthCheck(): array
    {
        $issues = [];
        $stats = $this->getStats();
        
        // Check for too many failed items
        $activityFailed = $stats['activity']['failed'] ?? 0;
        $deliveryFailed = $stats['delivery']['failed'] ?? 0;
        
        if ($activityFailed > 100) {
            $issues[] = "High number of failed activities: {$activityFailed}";
        }
        
        if ($deliveryFailed > 100) {
            $issues[] = "High number of failed deliveries: {$deliveryFailed}";
        }
        
        // Check for stuck processing items
        $activityProcessing = $stats['activity']['processing'] ?? 0;
        $deliveryProcessing = $stats['delivery']['processing'] ?? 0;
        
        if ($activityProcessing > 50) {
            $issues[] = "Many activities stuck in processing: {$activityProcessing}";
        }
        
        if ($deliveryProcessing > 50) {
            $issues[] = "Many deliveries stuck in processing: {$deliveryProcessing}";
        }
        
        return [
            'healthy' => empty($issues),
            'issues' => $issues,
        ];
    }
    
    /**
     * Get failed activities for inspection.
     * 
     * @param int $limit Maximum results
     * @return \Generator
     */
    public function getFailedActivities(int $limit = 50): \Generator
    {
        return ActivityQueue::getFailed($limit);
    }
    
    /**
     * Get failed deliveries for inspection.
     * 
     * @param int $limit Maximum results
     * @return \Generator
     */
    public function getFailedDeliveries(int $limit = 50): \Generator
    {
        return DeliveryQueue::getFailed($limit);
    }
}
