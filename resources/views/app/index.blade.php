@extends('layout')
@section('content')
    @if (isset($_REQUEST['error']))
        @if($_REQUEST['error']=='password')
        <div class="error btn btn-dangerrf" >Password is Incorrect</div>
        @endif
        @if($_REQUEST['error']=='datalink')
        <div class="error btn btn-dangerrf" >Password or Username Datalink is Incorrect</div>
        @endif
    @endif
<form action="" method="post">
    {{ csrf_field() }}
    <div class="form-group">
        <label for="store_id">Enter Your Store Url:</label>
        <input type="text" required name="store" class="form-control" id="store_id" placeholder="Your store for example: store.myshopify.com">
        <br>
         <input placeholder="Password (UpperCase, LowerCase, Number/SpecialChar and min 8 Chars)" type="text" pattern="(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$" required name="password" class="form-control" id="user_Pass" >
         <input placeholder="User name Datalink " type="text"  required name="Usernamedatalink" class="form-control"  >
         <input placeholder="User pass Datalink" type="text"  required name="UserpassDatalink" class="form-control"  >
        @if ($errors->has('store'))
            <div class="error">{{ $errors->first('store') }}</div>
        @endif
    </div>
    <button type="submit" class="btn btn-default">Submit</button>
</form>
@endsection