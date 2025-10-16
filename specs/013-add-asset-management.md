# Asset Management Tools

## Background

Currently, the MCP plugin provides comprehensive CRUD operations for Craft CMS entries but lacks equivalent functionality for assets and their underlying infrastructure. Assets in Craft CMS are files (images, documents, videos, etc.) that are managed through volumes and file systems. This specification outlines the implementation of comprehensive asset management tools that mirror the existing entry management pattern while addressing the unique requirements of file handling and infrastructure management.

Craft's asset system includes:
- **Assets**: The actual files and their metadata (title, alt text, custom fields)
- **Volumes**: Logical containers that define where assets are stored and how they're accessed
- **File Systems**: The underlying storage mechanism (local filesystem, Amazon S3, Google Cloud, etc.)
- **Asset Fields**: Custom fields that can be attached to assets (alt text, caption, etc.)
- **Transforms**: Image manipulation and optimization capabilities

## Goal

Implement a complete set of MCP tools for asset management that enables AI assistants to:
- Create, read, update, and delete assets
- Download assets from URLs or upload from file paths
- Create, read, update, and delete volumes
- Create, read, update, and delete file systems
- Manage the complete asset infrastructure stack

## Implementation Requirements

### Asset Management Tools

#### 1. Create Asset Tool
- **Primary Creation Method**: Download asset from provided URL
- **Secondary Creation Method**: Accept file path for local file upload
- **Volume Selection**: Require volumeId parameter to specify target volume
- **Field Support**: Accept custom field data for asset-specific fields
- **Filename Handling**: Support custom filename or auto-generate from URL
- **Error Handling**: Comprehensive error reporting for download failures, invalid files, permission issues

#### 2. Get Assets Tool
- **Asset Retrieval**: Fetch assets by optional array of IDs (returns all if no IDs provided)
- **Return Format**: Include file properties (size, dimensions, mimetype), URLs, custom fields
- **Transform URLs**: Provide access to image transform URLs when applicable
- **Volume Information**: Include volume details and file system information

#### 3. Update Asset Tool
- **Metadata Updates**: Allow updating title, alt text, and custom fields
- **File Replacement**: Support replacing the physical file while maintaining asset ID
- **Field Validation**: Respect field type constraints and validation rules

#### 4. Delete Asset Tool
- **Safe Deletion**: Remove asset record and associated files
- **Relationship Cleanup**: Handle removal from any referencing entries (handled automatically by Craft)
- **Confirmation Requirements**: Require explicit confirmation for destructive operations
- **Rollback Protection**: Provide clear error messages for failed deletions

### Volume Management Tools

#### 5. Create Volume Tool
- **Volume Configuration**: Create new volumes with specified settings
- **File System Assignment**: Link volume to existing file system
- **Field Layout Support**: Configure asset field layouts for the volume
- **Handle Generation**: Auto-generate unique handles or accept custom handles
- **Validation**: Ensure required settings are provided

#### 6. Get Volumes Tool
- **Volume Retrieval**: Fetch volumes by optional array of IDs (returns all if no IDs provided)
- **Return Format**: Include all volume settings, file system details, field layouts
- **Usage Information**: Show asset count and storage usage where available

#### 7. Update Volume Tool
- **Configuration Updates**: Modify volume settings and field layouts
- **File System Migration**: Support changing the underlying file system
- **Handle Updates**: Allow updating volume handles (with proper validation)
- **Field Layout Management**: Update asset field layouts for the volume

#### 8. Delete Volume Tool
- **Safe Deletion**: Remove volume only if no assets exist
- **Asset Check**: Verify volume is empty before deletion
- **Configuration Cleanup**: Remove associated field layouts and settings
- **Error Handling**: Clear error messages if deletion is blocked

### File System Management Tools

#### 9. Create File System Tool
- **Type Selection**: Support local, Amazon S3, Google Cloud Storage file systems
- **Configuration**: Accept type-specific configuration parameters
- **Validation**: Ensure required credentials and settings are provided
- **Connection Testing**: Optionally test connection during creation

#### 10. Get File Systems Tool
- **File System Retrieval**: Fetch file systems by optional array of IDs (returns all if no IDs provided)
- **Return Format**: Include type, settings, and connection status
- **Credential Handling**: Exclude sensitive credentials from responses

#### 11. Update File System Tool
- **Configuration Updates**: Modify file system settings
- **Credential Updates**: Update access keys and connection details
- **Type Migration**: Support changing file system types (with data migration warnings)
- **Connection Validation**: Test updated settings before saving

#### 12. Delete File System Tool
- **Safe Deletion**: Remove file system only if not used by any volumes
- **Usage Check**: Verify no volumes reference this file system
- **Cleanup**: Remove all associated configuration and credentials
- **Error Handling**: Clear error messages if deletion is blocked

## Technical Implementation Notes

### Asset Creation and File Handling
- Use `Craft::$app->getAssets()->saveAsset()` for asset persistence
- Implement URL downloads in our code but still call `->saveAsset()` to create the Asset record
- Handle temporary file creation and cleanup for file path uploads
- File type validation against volume's `allowedFileExtensions` happens automatically
- Volume constraints like `maxUploadFileSize` are enforced by Craft's internal validation

### Volume Management
- Use `Craft::$app->getVolumes()` service for volume operations
- Query volumes via `Craft::$app->getVolumes()->getAllVolumes()`
- Create volumes with `Craft::$app->getVolumes()->saveVolume()`
- Delete volumes with `Craft::$app->getVolumes()->deleteVolume()`
- Use volume's file system for storage operations
- Handle volume-specific settings (subfolder paths, transforms)

### File System Management
- Use `Craft::$app->getFs()` service for file system operations
- Support local, Amazon S3, and Google Cloud Storage file systems
- Create file systems with `Craft::$app->getFs()->saveFilesystem()`
- Delete file systems with `Craft::$app->getFs()->deleteFilesystem()`
- Handle file system-specific configuration parameters
- Exclude sensitive credentials from API responses

### Error Handling Patterns
- File download failures (network issues, invalid URLs, timeout)
- File validation failures reported by Craft's internal validation
- Permission failures (read-only volumes, user restrictions)
- Storage failures (disk space, connectivity issues)
- Use `ModelSaveException` pattern for all save/delete failures
- Volume/File System dependency errors (cannot delete if in use)

### Control Panel Integration
- Generate asset edit URLs using `ElementHelper::elementEditorUrl($asset)`
- Generate volume management URLs for volume tools
- Generate file system management URLs for file system tools
- Provide direct links to asset files for preview

### Security Considerations
- Trust user-provided URLs without pre-validation
- Allow Craft to handle filename sanitization
- Respect Craft's user permissions for all operations
- Trust user for file content validation
- Exclude sensitive file system credentials from responses

### Performance Considerations
- Implement timeout handling for URL downloads
- Use streaming for large file operations
- Cache volume and file system information appropriately
- Consider async processing for batch operations

## Non-Requirements (Future Considerations)

### Advanced Asset Management
- Bulk asset operations (batch upload, mass delete)
- Asset versioning and revision history
- Advanced image editing and transformation tools
- Asset workflow and approval processes
- Asset-specific search (provided by existing `search_content` tool)

### Integration Features
- Direct cloud storage integration (bypassing Craft volumes)
- Asset synchronization between environments
- External asset source integration (DAM systems)
- Asset analytics and usage tracking

### Transform Management
- Dynamic transform creation and management
- Batch transform generation tools
- Transform optimization and cleanup utilities

### Advanced File System Features
- Custom file system types beyond Craft's built-in options
- File system migration utilities with data transfer
- Advanced file system monitoring and health checks
- Multi-region file system configurations

## Acceptance Criteria

### Asset Management Tools
- [ ] `create_asset` tool successfully downloads files from URLs and creates assets
- [ ] `create_asset` tool accepts local file paths and creates assets
- [ ] `get_assets` tool retrieves assets by optional array of IDs (all assets if no IDs provided)
- [ ] `update_asset` tool modifies asset properties and custom fields
- [ ] `delete_asset` tool safely removes assets and associated files

### Volume Management Tools
- [ ] `create_volume` tool creates new volumes with proper configuration
- [ ] `get_volumes` tool retrieves volumes by optional array of IDs (all volumes if no IDs provided)
- [ ] `update_volume` tool modifies volume settings and field layouts
- [ ] `delete_volume` tool safely removes empty volumes

### File System Management Tools
- [ ] `create_file_system` tool creates new file systems (local, S3, GCS)
- [ ] `get_file_systems` tool retrieves file systems by optional array of IDs (all file systems if no IDs provided)
- [ ] `update_file_system` tool modifies file system settings
- [ ] `delete_file_system` tool safely removes unused file systems

### Test Coverage Requirements

#### Asset Management Tool Tests
- [ ] **CreateAssetTest.php**
  - [ ] Test successful asset creation from URL download
  - [ ] Test successful asset creation from local file path
  - [ ] Test asset creation with custom fields
  - [ ] Test asset creation with custom filename
  - [ ] Test URL download failure handling (invalid URL, network timeout)
  - [ ] Test file path validation (missing file, invalid permissions)
  - [ ] Test volume ID validation (nonexistent volume)
  - [ ] Test file type validation against volume constraints
  - [ ] Test control panel URL generation
  - [ ] Test ModelSaveException on asset save failure

- [ ] **GetAssetsTest.php**
  - [ ] Test retrieving all assets when no IDs provided
  - [ ] Test retrieving specific assets by ID array
  - [ ] Test asset properties in response (file size, dimensions, mimetype)
  - [ ] Test custom field inclusion in response
  - [ ] Test transform URL generation for images
  - [ ] Test volume information inclusion
  - [ ] Test handling of nonexistent asset IDs
  - [ ] Test empty result when no assets exist

- [ ] **UpdateAssetTest.php**
  - [ ] Test updating asset title and metadata
  - [ ] Test updating custom field values
  - [ ] Test file replacement functionality
  - [ ] Test field validation enforcement
  - [ ] Test nonexistent asset ID handling
  - [ ] Test control panel URL generation
  - [ ] Test ModelSaveException on update failure

- [ ] **DeleteAssetTest.php**
  - [ ] Test successful asset deletion
  - [ ] Test physical file removal verification
  - [ ] Test nonexistent asset ID handling
  - [ ] Test ModelSaveException on deletion failure
  - [ ] Test relationship cleanup (automatic by Craft)

#### Volume Management Tool Tests
- [ ] **CreateVolumeTest.php**
  - [ ] Test volume creation with local file system
  - [ ] Test volume creation with S3 file system
  - [ ] Test volume creation with custom field layout
  - [ ] Test handle auto-generation and custom handles
  - [ ] Test file system ID validation (nonexistent file system)
  - [ ] Test duplicate handle validation
  - [ ] Test control panel URL generation
  - [ ] Test ModelSaveException on save failure

- [ ] **GetVolumesTest.php**
  - [ ] Test retrieving all volumes when no IDs provided
  - [ ] Test retrieving specific volumes by ID array
  - [ ] Test volume configuration inclusion
  - [ ] Test file system details inclusion
  - [ ] Test field layout information inclusion
  - [ ] Test asset count and usage information
  - [ ] Test handling of nonexistent volume IDs
  - [ ] Test empty result when no volumes exist

- [ ] **UpdateVolumeTest.php**
  - [ ] Test volume settings modification
  - [ ] Test field layout updates
  - [ ] Test file system migration
  - [ ] Test handle updates with validation
  - [ ] Test nonexistent volume ID handling
  - [ ] Test control panel URL generation
  - [ ] Test ModelSaveException on update failure

- [ ] **DeleteVolumeTest.php**
  - [ ] Test successful empty volume deletion
  - [ ] Test deletion prevention when assets exist
  - [ ] Test field layout cleanup verification
  - [ ] Test nonexistent volume ID handling
  - [ ] Test ModelSaveException on deletion failure

#### File System Management Tool Tests
- [ ] **CreateFileSystemTest.php**
  - [ ] Test local file system creation
  - [ ] Test Amazon S3 file system creation with credentials
  - [ ] Test Google Cloud Storage file system creation
  - [ ] Test configuration parameter validation
  - [ ] Test handle auto-generation and custom handles
  - [ ] Test duplicate handle validation
  - [ ] Test connection testing (optional)
  - [ ] Test control panel URL generation
  - [ ] Test ModelSaveException on save failure

- [ ] **GetFileSystemsTest.php**
  - [ ] Test retrieving all file systems when no IDs provided
  - [ ] Test retrieving specific file systems by ID array
  - [ ] Test file system type and settings inclusion
  - [ ] Test credential exclusion from responses
  - [ ] Test connection status information
  - [ ] Test handling of nonexistent file system IDs
  - [ ] Test empty result when no file systems exist

- [ ] **UpdateFileSystemTest.php**
  - [ ] Test file system settings modification
  - [ ] Test credential updates
  - [ ] Test type migration warnings
  - [ ] Test connection validation on updates
  - [ ] Test nonexistent file system ID handling
  - [ ] Test control panel URL generation
  - [ ] Test ModelSaveException on update failure

- [ ] **DeleteFileSystemTest.php**
  - [ ] Test successful unused file system deletion
  - [ ] Test deletion prevention when volumes reference file system
  - [ ] Test configuration cleanup verification
  - [ ] Test nonexistent file system ID handling
  - [ ] Test ModelSaveException on deletion failure

#### Integration and Architecture Tests
- [ ] **AssetArchitectureTest.php**
  - [ ] Test all asset tools use dependency injection pattern
  - [ ] Test no direct container access in asset tool classes
  - [ ] Test all asset tools follow naming convention (snake_case, no craft_ prefix)
  - [ ] Test all asset tools extend proper base classes
  - [ ] Test ModelSaveException usage in all asset tools

#### Performance and Error Handling Tests
- [ ] **AssetDownloadTest.php**
  - [ ] Test URL download timeout handling
  - [ ] Test large file download streaming
  - [ ] Test network failure recovery
  - [ ] Test invalid content type handling
  - [ ] Test temporary file cleanup

- [ ] **AssetValidationTest.php**
  - [ ] Test file extension validation against volume settings
  - [ ] Test file size validation against volume limits
  - [ ] Test custom field validation rules
  - [ ] Test malformed data handling

#### Control Panel Integration Tests
- [ ] **AssetUrlGenerationTest.php**
  - [ ] Test asset edit URL generation
  - [ ] Test volume management URL generation
  - [ ] Test file system management URL generation
  - [ ] Test asset preview URL generation

### General Requirements
- [ ] All tools include proper error handling and validation
- [ ] All tools link back to Craft control panel for review
- [ ] Tools follow established naming conventions (snake_case, no craft_ prefix)
- [ ] Tools use dependency injection pattern consistently
- [ ] All tools include comprehensive test coverage as specified above
- [ ] PHPStan analysis passes at level max
- [ ] File download operations include timeout and error handling
- [ ] Dependency checking prevents deletion of in-use volumes/file systems
- [ ] Sensitive credentials are excluded from API responses
- [ ] Get tools follow GetSections/GetEntryTypes pattern with optional ID arrays
