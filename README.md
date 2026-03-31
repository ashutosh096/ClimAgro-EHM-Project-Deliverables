Q1: How to optimize video backgrounds for fast mobile loading

For mobile, I would avoid autoplay full HD videos directly.
Instead I would compress the video using HandBrake or FFmpeg and
export it in .mp4 format which is widely supported.
I would also use the video tag with a poster attribute so
users see an image while the video loads. For very slow connections,
I would just show the poster image and hide the video using a media
query. Lazy loading the video and keeping it under 5-8 MB helps
a lot with load time on mobile networks.

 Q2: How to leverage a CDN for serving global reports

A CDN basically stores copies of your files on servers around
the world. So if someone in the US requests a report PDF, they
get it from a nearby US server instead of one in India — which
makes it much faster. I would upload static assets like PDFs,
images, and JS files to a CDN like Cloudflare or AWS CloudFront.
The main benefit is reduced latency and the origin server doesn't
get overloaded. For EHM's reports, this would mean faster downloads
for international clients.
