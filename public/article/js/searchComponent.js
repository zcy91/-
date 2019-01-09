// 定义名为 todo-item 的新组件
Vue.component('todo-item', {
    props: {
        'resetPlaceholder': {
            type: String,
            default: function () {
                return '请输入搜索内容';
            }
        },
    },
    data () {
        return {
            styleObject: {
                color: 'red',
                display: 'flex',
                fontSize: '13px'
            },
            ifShow: false,
            inputValue: ''
        };
    },
    template: `
        <div class='common-search-wrapper'>
            <label for='search-input'>
              <i class="fa fa-search" aria-hidden="true"></i>
            </label>
            <input type='text' id='search-input' v-bind:placeholder='resetPlaceholder' v-model='inputValue' v-on:change='inputChange' v-on:input='inputIng'>
            <i class="fa fa-times-circle" aria-hidden="true"
              v-on:click='reset' v-if='ifShow'
            ></i>
            <span >重置</span>
        </div>
    `,
    methods: {
        changeValue: function (index) {
            // alert('index');
            this.$emit('change-event', index);
        },
        search: function () {
            this.$emit('search-event', this.inputValue);
        },
        // 重置搜索内容
        reset: function () {
            this.ifShow = false;
            this.inputValue = '';
            this.$emit('reset-event', this.inputValue);
        },
        inputChange: function () {
            console.log('change');
        },
        // 输入中执行
        inputIng: function () {
            console.log('inputting');
            if (this.ifShow === false) {
                this.ifShow = true;
            } else {
                return;
            }
        }
    }
});