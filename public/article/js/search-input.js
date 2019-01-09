/*
    * 搜索框
    * 用法：
    *     1 html中引入js css
    *     <script src="./vue-componets/search-input/search-input.js"></script>
    *     <link href="./vue-componets/search-input/search-input.css" rel="stylesheet">
    *     2 html：
    *         <todo-item v-bind:reset-placeholder='placeHolder' v-on:search-event='searchEvent'><todo-item>
    *     3 js：
    *       data: {
    *           // 定义默认搜索框placeholder 传递给组件覆盖prop值
                placeHolder: '安美拉D3',
                // 传递test-id给子组件
                idName: 'test-id'
            },
            methods: {
                // 监听搜索框emit来的value 执行后续操作
                searchEvent: function (value) {
                    console.log(value);
                }
            }

 */

Vue.component('todo-item', {
    props: {
        'resetPlaceholder': {
            type: String,
            default: function () {
                return '请输入搜索内容';
            }
        },
        'idName': {
            type: String,
            default: function () {
                return '';
            }
        }
    },
    data () {
        return {
            ifShow: false,
            inputValue: ''
        };
    },
    template: `
        <div class='vue-search-wrapper'>
            <input type='text' v-bind:id='idName' v-bind:placeholder='resetPlaceholder' v-model='inputValue'  v-on:input='inputIng'>
            <i class="fa fa-times-circle reset-btn" aria-hidden="true"
              v-on:click='reset' v-if='ifShow'
            ></i>
            <i  v-on:click='search' class="fa fa-search search-btn" aria-hidden="true"></i>
        </div>
    `,
    methods: {
        // 搜索提交执行
        search: function () {
            var keyword = !this.inputValue ? this.resetPlaceholder: this.inputValue; 
            this.$emit('search-event', keyword);
        },
        // 重置搜索内容
        reset: function () {
            this.ifShow = false;
            this.inputValue = '';
            // this.$emit('reset-event', this.inputValue);
        },
        // 输入中执行
        inputIng: function () {
            if (this.ifShow === false) {
                this.ifShow = true;
            } else {
                return;
            }
        }
    }
});