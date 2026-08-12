@extends('errors.layout')
@section('code', '403')
@section('title', 'Access Denied')
@section('message', $exception?->getMessage() ?: "You don't have permission to view this page. Contact an admin if you think this is a mistake.")
