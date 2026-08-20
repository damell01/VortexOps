<?php

namespace App\Exceptions;

/**
 * Whatnot refused the scraper at the edge, rather than the scrape going wrong.
 *
 * The distinction is worth a class because it decides whether carrying on makes
 * sense. A selector miss is about one page and the next channel may well work;
 * a bot challenge or a rate limit is about this machine and this session, and
 * every channel goes through the same browser to the same edge. Retrying the
 * other three took the same refusal four times over — twelve minutes of it —
 * and printed the same wall of diagnosis each time, which buries the first and
 * only useful copy.
 */
class WhatnotBlockedException extends \RuntimeException
{
}
