import { Table } from 'antd';

export default function LeaderboardTable({ players }) {
  return (
    <Table
      dataSource={players}
      columns={[
        { title: 'Rank', dataIndex: 'rank' },
        { title: 'Player', dataIndex: 'name' },
        { title: 'Points', dataIndex: 'points' },
        { title: 'Tournaments', dataIndex: 'tournaments' }
      ]}
    />
  );
}
