<?php

namespace Jeskew\StackPhPrerender;


class DefaultSettings
{
    public static $backendUrl = 'http://service.prerender.io/';

    public static $ignoredExtensions = [
        '.js',
        '.css',
        '.xml',
        '.less',
        '.png',
        '.jpg',
        '.jpeg',
        '.gif',
        '.pdf',
        '.doc',
        '.txt',
        '.ico',
        '.rss',
        '.zip',
        '.mp3',
        '.rar',
        '.exe',
        '.wmv',
        '.doc',
        '.avi',
        '.ppt',
        '.mpg',
        '.mpeg',
        '.tif',
        '.wav',
        '.mov',
        '.psd',
        '.ai',
        '.xls',
        '.mp4',
        '.m4a',
        '.swf',
        '.dat',
        '.dmg',
        '.iso',
        '.flv',
        '.m4v',
        '.torrent',
    ];

    // googlebot, yahoo, and bingbot are not in this list because
    // prerender.io supports _escaped_fragment_ and wants to ensure people aren't
    // penalized for cloaking.
    public static $botUserAgents = [
        // 'googlebot',
        // 'yahoo',
        // 'bingbot',
        'baiduspider',
        'facebookexternalhit',
        'twitterbot',
        'rogerbot',
        'linkedinbot',
        'embedly',
        'bufferbot',
        'quora link preview',
        'showyoubot',
        'outbrain'
    ];
} 