# Python Scraper Integration - Complete Example

This document provides a complete Python implementation for integrating your scraper with the WordPress Boat Chatbot plugin's vector database sync system.

## Setup

1. **Configure API Key** in WordPress Admin:
   - Go to Boat Chatbot Settings
   - Set "Sync API Key" to a secure random string
   - Save settings

2. **Install Python Dependencies**:
```bash
pip install requests
```

## Complete Implementation

```python
import requests
import time
from typing import List, Optional, Dict

class BoatChatbotSync:
    """
    Complete integration class for syncing and deleting records
    from SQL database to Pinecone vector database via WordPress REST API.
    """
    
    def __init__(self, wordpress_url: str, api_key: str):
        """
        Initialize the sync client.
        
        Args:
            wordpress_url: Your WordPress site URL (e.g., "https://yoursite.com")
            api_key: Your sync API key from WordPress admin settings
        """
        self.wordpress_url = wordpress_url.rstrip('/')
        self.api_key = api_key
        self.sync_endpoint = f"{self.wordpress_url}/wp-json/boat-chatbot/v1/sync-records"
        self.delete_endpoint = f"{self.wordpress_url}/wp-json/boat-chatbot/v1/delete-records"
    
    def sync_records(self, record_ids: Optional[List[int]] = None, 
                     sync_all: bool = False) -> Dict:
        """
        Sync records to Pinecone vector database.
        
        Args:
            record_ids: List of record IDs to sync (optional)
            sync_all: If True, sync all records (optional)
        
        Returns:
            dict: Response with success status and results
        """
        payload = {
            'api_key': self.api_key
        }
        
        if sync_all:
            payload['sync_all'] = True
        elif record_ids:
            payload['record_ids'] = record_ids
        
        try:
            response = requests.post(
                self.sync_endpoint,
                json=payload,
                timeout=600  # 10 minutes max for large batches
            )
            
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.Timeout:
            return {
                'success': False,
                'message': 'Request timed out. The sync may still be processing.',
                'error': 'Timeout after 600 seconds'
            }
        except requests.exceptions.HTTPError as e:
            error_msg = f'HTTP error {e.response.status_code}'
            try:
                error_data = e.response.json()
                if 'message' in error_data:
                    error_msg = error_data['message']
            except:
                error_msg = e.response.text[:200] if e.response.text else error_msg
            
            return {
                'success': False,
                'message': f'HTTP error: {error_msg}',
                'error': str(e)
            }
        except requests.exceptions.RequestException as e:
            return {
                'success': False,
                'message': f'Request failed: {str(e)}',
                'error': str(e)
            }
    
    def delete_records(self, record_ids: List[int]) -> Dict:
        """
        Delete records from Pinecone vector database.
        
        Args:
            record_ids: List of record IDs to delete (required)
        
        Returns:
            dict: Response with success status and results
        """
        if not record_ids or not isinstance(record_ids, list):
            return {
                'success': False,
                'message': 'record_ids must be a non-empty list',
                'error': 'Invalid input'
            }
        
        payload = {
            'api_key': self.api_key,
            'record_ids': record_ids
        }
        
        try:
            response = requests.post(
                self.delete_endpoint,
                json=payload,
                timeout=300  # 5 minutes max
            )
            
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.Timeout:
            return {
                'success': False,
                'message': 'Request timed out. The deletion may still be processing.',
                'error': 'Timeout after 300 seconds'
            }
        except requests.exceptions.HTTPError as e:
            error_msg = f'HTTP error {e.response.status_code}'
            try:
                error_data = e.response.json()
                if 'message' in error_data:
                    error_msg = error_data['message']
            except:
                error_msg = e.response.text[:200] if e.response.text else error_msg
            
            return {
                'success': False,
                'message': f'HTTP error: {error_msg}',
                'error': str(e)
            }
        except requests.exceptions.RequestException as e:
            return {
                'success': False,
                'message': f'Request failed: {str(e)}',
                'error': str(e)
            }
    
    def sync_after_update(self, updated_record_ids: List[int]) -> bool:
        """
        Convenience method to sync records after updating them in SQL database.
        
        Args:
            updated_record_ids: List of record IDs that were just updated
        
        Returns:
            bool: True if sync was successful
        """
        if not updated_record_ids:
            print("No records to sync")
            return True
        
        print(f"Syncing {len(updated_record_ids)} records to Pinecone...")
        result = self.sync_records(record_ids=updated_record_ids)
        
        if result.get('success'):
            results = result.get('results', {})
            print(f"✓ Sync completed: {results.get('success', 0)} successful, "
                  f"{results.get('failed', 0)} failed")
            return results.get('failed', 0) == 0
        else:
            print(f"✗ Sync failed: {result.get('message', 'Unknown error')}")
            if 'error' in result:
                print(f"  Error details: {result['error']}")
            return False
    
    def delete_after_removal(self, deleted_record_ids: List[int]) -> bool:
        """
        Convenience method to delete records from Pinecone after removing them from SQL database.
        
        Args:
            deleted_record_ids: List of record IDs that were just deleted from SQL
        
        Returns:
            bool: True if deletion was successful
        """
        if not deleted_record_ids:
            print("No records to delete")
            return True
        
        print(f"Deleting {len(deleted_record_ids)} records from Pinecone...")
        result = self.delete_records(deleted_record_ids)
        
        if result.get('success'):
            results = result.get('results', {})
            print(f"✓ Deletion completed: {results.get('success', 0)} successful, "
                  f"{results.get('failed', 0)} failed")
            
            # Print any errors if there were failures
            if results.get('failed', 0) > 0 and 'errors' in results:
                print("  Errors:")
                for error in results['errors'][:5]:  # Show first 5 errors
                    print(f"    - {error}")
                if len(results['errors']) > 5:
                    print(f"    ... and {len(results['errors']) - 5} more errors")
            
            return results.get('failed', 0) == 0
        else:
            print(f"✗ Deletion failed: {result.get('message', 'Unknown error')}")
            if 'error' in result:
                print(f"  Error details: {result['error']}")
            return False


# Usage Examples
if __name__ == "__main__":
    # Initialize
    sync = BoatChatbotSync(
        wordpress_url="https://yoursite.com",
        api_key="your_sync_api_key_here"
    )
    
    # Example 1: Sync records after updating SQL database
    updated_ids = [123, 456, 789]  # IDs you just updated
    sync.sync_after_update(updated_ids)
    
    # Example 2: Delete records after removing from SQL database
    deleted_ids = [111, 222, 333]  # IDs you just deleted
    sync.delete_after_removal(deleted_ids)
    
    # Example 3: Sync all records (use sparingly)
    result = sync.sync_records(sync_all=True)
    if result.get('success'):
        print("Full sync completed successfully")
    
    # Example 4: Sync specific records
    result = sync.sync_records(record_ids=[100, 200, 300])
    if result.get('success'):
        print("Sync completed")
    
    # Example 5: Delete specific records
    result = sync.delete_records([400, 500, 600])
    if result.get('success'):
        print("Deletion completed")


# Integration Example in Your Scraper
class YourScraper:
    """
    Example integration in your existing scraper.
    """
    
    def __init__(self):
        # Initialize your database connection
        self.db_connection = None  # Your DB connection
        
        # Initialize sync client
        self.sync = BoatChatbotSync(
            wordpress_url="https://yoursite.com",
            api_key="your_sync_api_key_here"
        )
    
    def update_record(self, record_id: int, data: dict):
        """Update record in SQL and sync to Pinecone"""
        
        # 1. Update SQL database
        # ... your SQL update code here ...
        print(f"Updating record {record_id} in SQL database...")
        # self.db_connection.execute(...)
        
        # 2. Sync to Pinecone
        self.sync.sync_after_update([record_id])
        
        print(f"Record {record_id} updated and synced")
    
    def delete_record(self, record_id: int):
        """Delete record from SQL and remove from Pinecone"""
        
        # 1. Delete from SQL database
        # ... your SQL delete code here ...
        print(f"Deleting record {record_id} from SQL database...")
        # self.db_connection.execute(...)
        
        # 2. Delete from Pinecone
        self.sync.delete_after_removal([record_id])
        
        print(f"Record {record_id} deleted from both SQL and Pinecone")
    
    def batch_update(self, updates: List[tuple]):
        """
        Batch update multiple records.
        
        Args:
            updates: List of (record_id, data) tuples
        """
        updated_ids = []
        
        for record_id, data in updates:
            # Update SQL database
            # ... your SQL update code here ...
            updated_ids.append(record_id)
        
        # Sync all updated records in one batch
        if updated_ids:
            self.sync.sync_after_update(updated_ids)
    
    def batch_delete(self, record_ids: List[int]):
        """
        Batch delete multiple records.
        
        Args:
            record_ids: List of record IDs to delete
        """
        # Delete from SQL database
        # ... your SQL delete code here ...
        
        # Delete from Pinecone
        if record_ids:
            self.sync.delete_after_removal(record_ids)


# Error Handling Best Practices
def safe_sync_with_retry(sync_client: BoatChatbotSync, record_ids: List[int], 
                         max_retries: int = 3) -> bool:
    """
    Sync records with automatic retry on failure.
    
    Args:
        sync_client: Initialized BoatChatbotSync instance
        record_ids: List of record IDs to sync
        max_retries: Maximum number of retry attempts
    
    Returns:
        bool: True if sync succeeded (eventually)
    """
    for attempt in range(max_retries):
        result = sync_client.sync_records(record_ids=record_ids)
        
        if result.get('success'):
            return True
        
        if attempt < max_retries - 1:
            wait_time = (attempt + 1) * 5  # Exponential backoff: 5s, 10s, 15s
            print(f"Sync failed, retrying in {wait_time} seconds... (attempt {attempt + 1}/{max_retries})")
            time.sleep(wait_time)
        else:
            print(f"Sync failed after {max_retries} attempts")
            return False
    
    return False


# Notes
"""
API Endpoints:
- POST /wp-json/boat-chatbot/v1/sync-records
  - Body: {'api_key': '...', 'record_ids': [1, 2, 3]} or {'api_key': '...', 'sync_all': True}
  - Syncs records to Pinecone vector database
  
- POST /wp-json/boat-chatbot/v1/delete-records
  - Body: {'api_key': '...', 'record_ids': [1, 2, 3]}
  - Deletes records from Pinecone vector database

Best Practices:
1. Always sync after updating SQL database
2. Always delete from Pinecone after removing from SQL database
3. Use batch operations when possible (more efficient)
4. Implement retry logic for production use
5. Log all sync/delete operations for debugging
6. Monitor sync status in WordPress admin panel
7. Use appropriate timeouts for large batches
8. Handle errors gracefully and notify administrators

Performance Tips:
- Batch operations are more efficient than individual calls
- Sync in batches of 50-100 records for optimal performance
- Delete operations are faster than sync operations
- Consider rate limiting if making many requests quickly
"""

