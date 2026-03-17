import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Menu } from 'antd';
import { UnorderedListOutlined, OrderedListOutlined } from '@ant-design/icons';
import { Divider, Flex, Tag } from 'antd';
const items = [
  {
    key: 'dashboard',
    label: <Link to="/">Leaderboard</Link>,
    icon: <OrderedListOutlined />
  },
  {
    key: 'tournaments',
    label: <Link to="/tournaments">Tournaments</Link>,
    icon: <UnorderedListOutlined />
  }
];

export default function NavBar() {
  const [current, setCurrent] = useState('dashboard');

  const onClick = (e) => {
    setCurrent(e.key);
  };

  return <Menu onClick={onClick} selectedKeys={[current]} mode="horizontal" items={items} className="w-full mb-4! p-1!" />;
}
