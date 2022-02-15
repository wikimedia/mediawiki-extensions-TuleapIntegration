# Authentication

Authentication of communication between wiki farm and Tuleap app is done based on the salted pre-shared key.
Both parties need to have the same key set. On wiki side, set it using `$wgTuleapPreSharedKey = ''`

Wiki expects a Bearer token with uses SHA-256 hash of the pre-shared key + current timestamp (UNIX).
Difference of 10 seconds in the timestamp between machines is acceptable.
