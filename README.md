#shoogle tweet

shoogle-tweet is a mediawiki-extension to display a twitter feed for a given
screen_name. It caches the response in 2 tiers. First for the normal display, 
so the twitter api isnt "crawled" every display - and the second tier for
high availability.

Sometimes the twitter api is not available - which resulted in a timeout and
therefore a slow website response. 

shoogle tweet resolves this issue ;)

todo:
* cache html instead of json data
* test, test, test
