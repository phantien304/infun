<div class="comment-form">
    <h4 class="mb-15">Viết đánh giá</h4>
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <form action="{!! route('review.saveReview') !!}" method="post" id="ratingForm"
                  enctype="multipart/form-data">
                <div class="row">
                    <div class="col-12 mb-10">
                        <div class="form-group">
                            <div class="rating">
                                <input type="radio" id="star5" name="rating" value="5"/>
                                <label for="star5" title="Rocks!">5 stars</label>
                                <input type="radio" id="star4" name="rating" value="4"/>
                                <label for="star4" title="Pretty good">4 stars</label>
                                <input type="radio" id="star3" name="rating" value="3"/>
                                <label for="star3" title="Meh">3 stars</label>
                                <input type="radio" id="star2" name="rating" value="2"/>
                                <label for="star2" title="Kinda bad">2 stars</label>
                                <input type="radio" id="star1" name="rating" value="1"/>
                                <label for="star1" title="Sucks big time">1 star</label>
                                <div id="rating" style="font-size: 13px;"></div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="product_id" value="{{ $entity->id }}"/>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <input class="form-control" name="author"
                                   id="author" type="text" placeholder="Họ tên (*)"
                                   value="@if(auth()->check()){{ auth()->user()->full_name }}@endif">
                            <div id="author" style="font-size: 13px;"></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <input class="form-control" name="email" id="email" type="email" placeholder="Email"
                                   value="@if(auth()->check()){{ auth()->user()->email }}@endif">
                            <div id="email" style="font-size: 13px;"></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <textarea class="form-control w-100" name="text" id="text" cols="30"
                                      rows="9" placeholder="Nhận xét (*)"></textarea>
                            <div id="text" style="font-size: 13px;"></div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="button button-contactForm">Đánh giá</button>
                </div>
            </form>
        </div>
    </div>
</div>
