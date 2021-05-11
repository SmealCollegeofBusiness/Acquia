# Acquia Content Hub S3 Integration

This module supposed to serve as a bridge between s3fs and Content Hub.
First please make sure s3fs and `acquia_contenthub` module set up correctly.

The module supports multi-bucket and multi-root folder syndication.

**What does this mean?**

S3fs out of the box currently cannot handle multiple buckets due to its configuration limitations.
However `acquia_contenthub_s3` takes into account that in case of multiple sites these configurations could differ.
It stores this information in a mapping table called `acquia_contenthub_s3_file_map`, so later these s3 uris, the
files' locations, could be identified and resolved on the receiving side.

On the first installation all files that are stored in the s3 bucket are going to be cached. This might take a while.
This is necessary so that later we can avoid redundant requests towards Content Hub due to file location discovery.

**File location discovery**

The module's responsibility is to locate the files (on the subscriber, and the publisher) that are being syndicated
through Content Hub. The first source of information is the mapping table, Content Hub will follow, and lastly it'll
assume local origination. The module will come down to conclusion that the file owned by the site in hand.
In order to be successful in location discovery the module introduced two new CDF attributes: `ach_bucket` and
`ach_root_folder`. Therefore, already exported file entities must be syndicated again so that they will contain these
essential properties.
